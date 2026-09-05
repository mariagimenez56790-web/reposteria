<?php

namespace Tests\Feature;

use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmpleadoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_employee_routes_require_authentication_and_active_user(): void
    {
        $this->getJson('/api/v1/reposterias/1/empleados')->assertUnauthorized();
        [$reposteria] = $this->contexto();
        Sanctum::actingAs($this->usuario('admin', false));
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/empleados")->assertForbidden();
    }

    public function test_admin_and_superadmin_list_but_other_roles_are_blocked(): void
    {
        [$reposteria, $admin] = $this->contexto();
        foreach ([$admin, $this->usuario('superadmin')] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/empleados")->assertOk()->assertJsonPath('meta.total', 1);
        }
        foreach ([$this->miembro($reposteria, 'vendedor'), $this->miembro($reposteria, 'produccion'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/empleados")->assertForbidden();
        }
    }

    public function test_listing_searches_filters_paginates_and_only_returns_members(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $ana = $this->miembro($reposteria, 'vendedor', ['name' => 'Ana Dulce', 'email' => 'ana.dulce@example.test', 'activo' => true]);
        $this->miembro($reposteria, 'vendedor', ['name' => 'Ana Inactiva', 'activo' => false]);
        $ajeno = $this->usuario('vendedor', true, ['name' => 'Ana Ajena', 'email' => 'ajena@example.test']);
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/empleados?search=ana.dulce&activo=1&rol=vendedor&per_page=1")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ana->id)->assertJsonMissing(['id' => $ajeno->id])->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.remember_token')->assertJsonMissingPath('data.0.tokens');
    }

    public function test_detail_is_safe_and_tenant_scoped_for_admin_and_superadmin(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $empleadoA = $this->miembro($a, 'produccion');
        $empleadoB = $this->miembro($b, 'vendedor');
        foreach ([$adminA, $this->usuario('superadmin')] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$a->id}/empleados/{$empleadoA->id}")->assertOk()->assertJsonPath('data.rol', 'produccion')->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.role_id');
        }
        Sanctum::actingAs($adminA);
        $this->getJson("/api/v1/reposterias/{$a->id}/empleados/{$empleadoB->id}")->assertNotFound();
    }

    public function test_admin_creates_all_internal_roles_with_hashed_password_and_membership(): void
    {
        [$reposteria, $admin] = $this->contexto();
        Sanctum::actingAs($admin);
        foreach (['admin', 'vendedor', 'produccion'] as $rol) {
            $email = "{$rol}@example.test";
            $respuesta = $this->postJson("/api/v1/reposterias/{$reposteria->id}/empleados", ['name' => ucfirst($rol), 'email' => $email, 'password' => 'Clave-segura-123', 'rol' => $rol, 'role_id' => Role::where('nombre', 'superadmin')->firstOrFail()->id])
                ->assertCreated()->assertJsonPath('data.rol', $rol)->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.role_id');
            $usuario = User::findOrFail($respuesta->json('data.id'));
            $this->assertTrue(Hash::check('Clave-segura-123', $usuario->password));
            $this->assertTrue($usuario->perteneceAReposteria($reposteria));
        }
    }

    public function test_superadmin_creates_employee_without_membership(): void
    {
        [$reposteria] = $this->contexto();
        $superadmin = $this->usuario('superadmin');
        Sanctum::actingAs($superadmin);
        $id = $this->postJson("/api/v1/reposterias/{$reposteria->id}/empleados", ['name' => 'Nuevo', 'email' => 'nuevo@example.test', 'password' => 'Clave-segura-123', 'rol' => 'vendedor'])->assertCreated()->json('data.id');
        $this->assertTrue(User::findOrFail($id)->perteneceAReposteria($reposteria));
        $this->assertFalse($superadmin->perteneceAReposteria($reposteria));
    }

    public function test_creation_rejects_invalid_duplicate_email_and_forbidden_roles_atomically(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $existente = $this->usuario('vendedor', true, ['email' => 'existente@example.test']);
        Sanctum::actingAs($admin);
        foreach ([
            ['email' => 'invalido'],
            ['email' => $existente->email],
            ['rol' => 'superadmin'],
            ['rol' => 'cliente'],
        ] as $cambio) {
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/empleados", array_replace(['name' => 'Inválido', 'email' => fake()->unique()->safeEmail(), 'password' => 'Clave-segura-123', 'rol' => 'vendedor'], $cambio))->assertUnprocessable();
        }
        $this->assertDatabaseCount('reposteria_user', 1);
    }

    public function test_non_admin_roles_cannot_create_employees(): void
    {
        [$reposteria] = $this->contexto();
        foreach ([$this->miembro($reposteria, 'vendedor'), $this->miembro($reposteria, 'produccion'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/empleados", ['name' => 'No', 'email' => fake()->unique()->safeEmail(), 'password' => 'Clave-segura-123', 'rol' => 'vendedor'])->assertForbidden();
        }
    }

    public function test_patch_updates_name_email_active_and_global_internal_role(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $empleado = $this->miembro($reposteria, 'vendedor');
        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/empleados/{$empleado->id}", ['name' => 'Nombre nuevo'])->assertOk()->assertJsonPath('data.nombre', 'Nombre nuevo')->assertJsonPath('data.rol', 'vendedor');
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/empleados/{$empleado->id}", ['email' => 'NUEVO@EXAMPLE.TEST', 'rol' => 'produccion'])->assertOk()->assertJsonPath('data.email', 'nuevo@example.test')->assertJsonPath('data.rol', 'produccion');
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/empleados/{$empleado->id}", ['rol' => 'admin'])->assertOk()->assertJsonPath('data.rol', 'admin');
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/empleados/{$empleado->id}", ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);
    }

    public function test_patch_rejects_forbidden_roles_duplicate_email_and_foreign_employee(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $empleadoA = $this->miembro($a, 'vendedor');
        $empleadoB = $this->miembro($b, 'produccion');
        $otro = $this->usuario('vendedor');
        Sanctum::actingAs($adminA);
        foreach (['superadmin', 'cliente'] as $rol) {
            $this->patchJson("/api/v1/reposterias/{$a->id}/empleados/{$empleadoA->id}", ['rol' => $rol])->assertUnprocessable();
        }
        $this->patchJson("/api/v1/reposterias/{$a->id}/empleados/{$empleadoA->id}", ['email' => $otro->email])->assertUnprocessable();
        $this->patchJson("/api/v1/reposterias/{$a->id}/empleados/{$empleadoB->id}", ['name' => 'Hack'])->assertNotFound();
    }

    public function test_owner_and_self_administration_are_protected(): void
    {
        [$reposteria, $propietario] = $this->contexto();
        Sanctum::actingAs($propietario);
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/empleados/{$propietario->id}", ['activo' => false])->assertUnprocessable();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/empleados/{$propietario->id}", ['rol' => 'vendedor'])->assertUnprocessable();
        $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/empleados/{$propietario->id}")->assertUnprocessable();
        $this->assertTrue($propietario->fresh()->activo);
        $this->assertSame('admin', $propietario->fresh()->role->nombre);
        $this->assertTrue($propietario->perteneceAReposteria($reposteria));
    }

    public function test_admin_and_superadmin_remove_normal_membership_without_deleting_user(): void
    {
        foreach (['admin', 'superadmin'] as $rol) {
            [$reposteria, $admin] = $this->contexto();
            $empleado = $this->miembro($reposteria, 'vendedor');
            $actor = $rol === 'admin' ? $admin : $this->usuario('superadmin');
            Sanctum::actingAs($actor);
            $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/empleados/{$empleado->id}")->assertNoContent();
            $this->assertFalse($empleado->perteneceAReposteria($reposteria));
            $this->assertNotNull(User::find($empleado->id));
        }
    }

    public function test_removing_one_membership_preserves_another_and_cross_tenant_delete_is_hidden(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $empleado = $this->miembro($a, 'vendedor');
        $b->usuarios()->attach($empleado);
        Sanctum::actingAs($adminA);
        $this->deleteJson("/api/v1/reposterias/{$a->id}/empleados/{$empleado->id}")->assertNoContent();
        $this->assertFalse($empleado->perteneceAReposteria($a));
        $this->assertTrue($empleado->perteneceAReposteria($b));
        $this->deleteJson("/api/v1/reposterias/{$a->id}/empleados/{$empleado->id}")->assertNotFound();
    }

    public function test_vendor_production_and_client_cannot_remove_members(): void
    {
        [$reposteria] = $this->contexto();
        $empleado = $this->miembro($reposteria, 'vendedor');
        foreach ([$this->miembro($reposteria, 'vendedor'), $this->miembro($reposteria, 'produccion'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/empleados/{$empleado->id}")->assertForbidden();
        }
        $this->assertTrue($empleado->perteneceAReposteria($reposteria));
    }

    private function contexto(): array
    {
        $admin = $this->usuario('admin');
        $reposteria = Reposteria::factory()->for($admin, 'propietario')->create();

        return [app(ReposteriaEstadoService::class)->aprobar($reposteria, $this->usuario('superadmin')), $admin];
    }

    private function miembro(Reposteria $reposteria, string $rol, array $atributos = []): User
    {
        $usuario = $this->usuario($rol, $atributos['activo'] ?? true, $atributos);
        $reposteria->usuarios()->attach($usuario);

        return $usuario;
    }

    private function usuario(string $rol, bool $activo = true, array $atributos = []): User
    {
        return User::factory()->for(Role::query()->where('nombre', $rol)->firstOrFail())->create($atributos + ['activo' => $activo]);
    }
}
