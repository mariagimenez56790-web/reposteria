<?php

namespace Tests\Feature;

use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReposteriaPerfilApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_profile_routes_require_authentication_and_active_user(): void
    {
        $reposteria = $this->reposteriaAprobada();

        $this->getJson("/api/v1/reposterias/{$reposteria->id}/perfil")->assertUnauthorized();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", ['nombre' => 'Nuevo'])->assertUnauthorized();

        Sanctum::actingAs($this->usuario('admin', false));
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/perfil")->assertForbidden();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", ['nombre' => 'Nuevo'])->assertForbidden();
    }

    public function test_admin_can_view_and_partially_update_own_approved_bakery(): void
    {
        [$reposteria, $admin] = $this->reposteriaAprobadaConAdmin();
        $descripcion = $reposteria->descripcion;
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/reposterias/{$reposteria->id}/perfil")
            ->assertOk()
            ->assertJsonPath('data.id', $reposteria->id)
            ->assertJsonPath('data.estado', 'aprobada');

        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", ['nombre' => '  Dulce Encanto  '])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Dulce Encanto')
            ->assertJsonPath('data.descripcion', $descripcion);

        $this->assertDatabaseHas('reposterias', [
            'id' => $reposteria->id,
            'nombre' => 'Dulce Encanto',
            'descripcion' => $descripcion,
        ]);
    }

    public function test_superadmin_can_view_and_update_without_membership(): void
    {
        $reposteria = $this->reposteriaAprobada();
        $superadmin = $this->usuario('superadmin');
        $this->assertFalse($superadmin->perteneceAReposteria($reposteria));
        Sanctum::actingAs($superadmin);

        $this->getJson("/api/v1/reposterias/{$reposteria->id}/perfil")->assertOk();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", [
            'telefono' => '  70000000  ',
            'email' => '  perfil@example.test  ',
            'direccion' => '  Calle Central  ',
            'ciudad' => '  La Paz  ',
        ])->assertOk()
            ->assertJsonPath('data.telefono', '70000000')
            ->assertJsonPath('data.email', 'perfil@example.test')
            ->assertJsonPath('data.direccion', 'Calle Central')
            ->assertJsonPath('data.ciudad', 'La Paz');
    }

    public function test_non_administrative_roles_cannot_view_or_update_profile(): void
    {
        $reposteria = $this->reposteriaAprobada();

        foreach (['vendedor', 'produccion', 'cliente'] as $rol) {
            $actor = $this->usuario($rol);
            $reposteria->usuarios()->syncWithoutDetaching([$actor->id]);
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/perfil")->assertForbidden();
            $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", ['nombre' => 'Prohibido'])->assertForbidden();
        }
    }

    public function test_admin_cannot_access_another_bakery_and_external_user_cannot_access(): void
    {
        [$propia, $admin] = $this->reposteriaAprobadaConAdmin();
        $ajena = $this->reposteriaAprobada();
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/reposterias/{$ajena->id}/perfil")->assertForbidden();
        $this->patchJson("/api/v1/reposterias/{$ajena->id}/perfil", ['nombre' => 'Intrusión'])->assertForbidden();
        $this->assertNotSame('Intrusión', $ajena->fresh()->nombre);

        $externo = $this->usuario('admin');
        Sanctum::actingAs($externo);
        $this->getJson("/api/v1/reposterias/{$propia->id}/perfil")->assertForbidden();
    }

    public function test_profile_resource_only_exposes_safe_fields_and_iso_dates(): void
    {
        $reposteria = $this->reposteriaAprobada();
        Sanctum::actingAs($this->usuario('superadmin'));

        $respuesta = $this->getJson("/api/v1/reposterias/{$reposteria->id}/perfil")->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'nombre', 'slug', 'descripcion', 'logo', 'portada', 'telefono',
                'email', 'direccion', 'ciudad', 'estado', 'created_at', 'updated_at',
            ]])
            ->assertJsonMissingPath('data.propietario_id')
            ->assertJsonMissingPath('data.aprobada_por')
            ->assertJsonMissingPath('data.fecha_aprobacion')
            ->assertJsonMissingPath('data.motivo_estado')
            ->assertJsonMissingPath('data.deleted_at');

        $this->assertNotFalse(date_create($respuesta->json('data.created_at')));
        $this->assertNotFalse(date_create($respuesta->json('data.updated_at')));
    }

    public function test_all_editable_fields_validate_and_nullable_fields_can_be_cleared(): void
    {
        [$reposteria, $admin] = $this->reposteriaAprobadaConAdmin();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", [
            'nombre' => '   ',
            'email' => 'correo-invalido',
        ])->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'email']);

        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", [
            'descripcion' => null,
            'telefono' => null,
            'email' => null,
            'direccion' => null,
            'ciudad' => null,
        ])->assertOk()
            ->assertJsonPath('data.descripcion', null)
            ->assertJsonPath('data.telefono', null)
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.direccion', null)
            ->assertJsonPath('data.ciudad', null);
    }

    public function test_slug_and_administrative_fields_are_rejected_without_changes(): void
    {
        $reposteria = $this->reposteriaAprobada();
        $original = $reposteria->getAttributes();
        Sanctum::actingAs($this->usuario('superadmin'));

        $protegidos = [
            'id' => 999,
            'slug' => 'slug-alterado',
            'logo' => 'otro-logo.png',
            'portada' => 'otra-portada.png',
            'estado' => 'inactiva',
            'propietario_id' => 999,
            'aprobada_por' => 999,
            'fecha_aprobacion' => now()->subYear()->toISOString(),
            'motivo_estado' => 'Alterado',
            'created_at' => now()->subYear()->toISOString(),
            'updated_at' => now()->subYear()->toISOString(),
            'deleted_at' => now()->toISOString(),
        ];

        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", $protegidos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($protegidos));

        $actual = $reposteria->fresh()->getAttributes();
        foreach (array_keys($protegidos) as $campo) {
            $this->assertSame($original[$campo] ?? null, $actual[$campo] ?? null, $campo);
        }
    }

    public function test_local_admin_cannot_manage_non_operational_bakeries_but_superadmin_can(): void
    {
        $superadmin = $this->usuario('superadmin');
        $estados = app(ReposteriaEstadoService::class);
        $pendiente = $this->reposteriaPendiente();
        $rechazada = $estados->rechazar($this->reposteriaPendiente(), $superadmin, 'Rechazo');
        $suspendida = $estados->suspender($estados->aprobar($this->reposteriaPendiente(), $superadmin), $superadmin, 'Pausa');
        $inactiva = $estados->inactivar($this->reposteriaPendiente(), $superadmin, 'Cierre');

        foreach ([$pendiente, $rechazada, $suspendida, $inactiva] as $reposteria) {
            Sanctum::actingAs($reposteria->propietario);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/perfil")->assertUnprocessable();
            $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", ['nombre' => 'No permitido'])->assertUnprocessable();

            Sanctum::actingAs($superadmin);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/perfil")->assertOk();
            $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", ['nombre' => 'Gestión global'])->assertOk();
        }
    }

    public function test_missing_and_soft_deleted_bakeries_are_not_accessible(): void
    {
        Sanctum::actingAs($this->usuario('superadmin'));
        $this->getJson('/api/v1/reposterias/999999/perfil')->assertNotFound();

        $reposteria = $this->reposteriaAprobada();
        $reposteria->delete();
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/perfil")->assertNotFound();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/perfil", ['nombre' => 'No'])->assertNotFound();
    }

    private function reposteriaAprobada(): Reposteria
    {
        return app(ReposteriaEstadoService::class)->aprobar(
            $this->reposteriaPendiente(),
            $this->usuario('superadmin'),
        );
    }

    /** @return array{Reposteria, User} */
    private function reposteriaAprobadaConAdmin(): array
    {
        $reposteria = $this->reposteriaAprobada();

        return [$reposteria, $reposteria->propietario];
    }

    private function reposteriaPendiente(): Reposteria
    {
        return Reposteria::factory()->for($this->usuario('admin'), 'propietario')->create();
    }

    private function usuario(string $rol, bool $activo = true): User
    {
        return User::factory()->for(Role::query()->where('nombre', $rol)->firstOrFail())->create(['activo' => $activo]);
    }
}
