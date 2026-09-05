<?php

namespace Tests\Feature;

use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductoService;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        RateLimiter::clear('');
    }

    public function test_login_route_returns_bearer_token_safe_user_and_relevant_bakeries(): void
    {
        $usuario = $this->usuario('admin');
        $aprobada = $this->reposteria($usuario, true);
        $this->reposteria($usuario, false);

        $respuesta = $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'clave-segura', 'device_name' => 'Samsung A24', 'role_id' => 999, 'activo' => false]);

        $respuesta->assertOk()->assertJsonPath('message', 'Inicio de sesión correcto.')->assertJsonPath('data.token_type', 'Bearer')->assertJsonPath('data.user.role', 'admin')->assertJsonPath('data.user.reposterias.0.id', $aprobada->id)->assertJsonCount(1, 'data.user.reposterias')->assertJsonMissingPath('data.user.password')->assertJsonMissingPath('data.user.remember_token');
        $this->assertNotEmpty($respuesta->json('data.token'));
        $this->assertSame('admin', $usuario->fresh()->role->nombre);
        $this->assertTrue($usuario->fresh()->activo);
        $this->assertSame('Samsung A24', $usuario->tokens()->firstOrFail()->name);
    }

    public function test_login_validation_requires_valid_email_password_and_device_name(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/login', ['email' => 'invalido', 'password' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/login', ['email' => 'a@b.com', 'password' => 'x', 'device_name' => str_repeat('x', 101)])->assertUnprocessable()->assertJsonValidationErrors('device_name');
    }

    public function test_wrong_email_and_password_return_same_generic_response_without_token(): void
    {
        $usuario = $this->usuario('admin');
        foreach ([['email' => 'nadie@example.com', 'password' => 'incorrecta'], ['email' => $usuario->email, 'password' => 'incorrecta']] as $credenciales) {
            $this->postJson('/api/login', $credenciales)->assertUnauthorized()->assertExactJson(['message' => 'Credenciales incorrectas.']);
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_login_or_use_an_existing_token(): void
    {
        $usuario = $this->usuario('vendedor');
        $token = $usuario->createToken('anterior')->plainTextToken;
        $usuario->forceFill(['activo' => false])->save();
        $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'clave-segura'])->assertForbidden()->assertExactJson(['message' => 'La cuenta no está disponible.']);
        $this->withToken($token)->getJson('/api/me')->assertForbidden()->assertExactJson(['message' => 'La cuenta no está disponible.']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_all_active_roles_can_authenticate_and_superadmin_has_no_bakery_list(): void
    {
        foreach (['superadmin', 'admin', 'vendedor', 'produccion', 'cliente'] as $rol) {
            $usuario = $this->usuario($rol);
            $respuesta = $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'clave-segura']);
            $respuesta->assertOk()->assertJsonPath('data.user.role', $rol);
            if ($rol === 'superadmin') {
                $respuesta->assertJsonCount(0, 'data.user.reposterias');
            }
        }
    }

    public function test_plain_token_is_returned_once_and_only_hash_is_stored(): void
    {
        $usuario = $this->usuario('cliente');
        $plano = $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'clave-segura'])->json('data.token');
        [, $secreto] = explode('|', $plano, 2);
        $almacenado = DB::table('personal_access_tokens')->value('token');
        $this->assertNotSame($plano, $almacenado);
        $this->assertNotSame($secreto, $almacenado);
        $this->assertSame(hash('sha256', $secreto), $almacenado);
    }

    public function test_me_and_logout_require_sanctum_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
        $usuario = $this->usuario('produccion');
        $token = $usuario->createToken('flutter')->plainTextToken;
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.role', 'produccion')->assertJsonMissingPath('data.password');
    }

    public function test_logout_revokes_only_current_token_and_other_device_remains_valid(): void
    {
        $usuario = $this->usuario('admin');
        $primero = $usuario->createToken('celular')->plainTextToken;
        $segundo = $usuario->createToken('pc')->plainTextToken;
        $this->withToken($primero)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->app['auth']->forgetGuards();
        $this->withToken($primero)->getJson('/api/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($segundo)->getJson('/api/me')->assertOk();
    }

    public function test_login_is_rate_limited_after_five_attempts_per_email_and_ip(): void
    {
        for ($intento = 1; $intento <= 5; $intento++) {
            $this->postJson('/api/login', ['email' => 'ataque@example.com', 'password' => 'incorrecta'])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => 'ataque@example.com', 'password' => 'incorrecta'])->assertStatus(429);
    }

    public function test_suspended_bakery_stays_blocked_despite_valid_token(): void
    {
        $admin = $this->usuario('admin');
        $reposteria = $this->reposteria($admin, true);
        $token = $admin->createToken('flutter')->plainTextToken;
        app(ReposteriaEstadoService::class)->suspender($reposteria, $this->usuario('superadmin'), 'Revisión');
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonCount(0, 'data.reposterias');
        $this->expectException(DomainException::class);
        app(ProductoService::class)->crear($admin, $reposteria->fresh(), ['nombre' => 'Torta', 'precio' => 10, 'personalizable' => false, 'maneja_stock' => false, 'stock' => 0]);
    }

    private function usuario(string $rol, bool $activo = true): User
    {
        return User::factory()->for(Role::query()->where('nombre', $rol)->firstOrFail())->create(['password' => Hash::make('clave-segura'), 'activo' => $activo]);
    }

    private function reposteria(User $usuario, bool $aprobar): Reposteria
    {
        $reposteria = Reposteria::factory()->for($usuario, 'propietario')->create();

        return $aprobar ? app(ReposteriaEstadoService::class)->aprobar($reposteria, $this->usuario('superadmin')) : $reposteria;
    }
}
