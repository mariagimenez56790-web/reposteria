<?php

namespace Tests\Feature;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReposteriaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_bakery_routes_require_authentication_and_active_user(): void
    {
        $this->getJson('/api/v1/admin/reposterias')->assertUnauthorized();
        Sanctum::actingAs($this->usuario('superadmin', false));
        $this->getJson('/api/v1/admin/reposterias')->assertForbidden();
    }

    public function test_only_superadmin_can_list_and_view_global_bakeries(): void
    {
        $reposteria = $this->reposteriaPendiente();
        $superadmin = $this->usuario('superadmin');
        Sanctum::actingAs($superadmin);
        $this->getJson('/api/v1/admin/reposterias')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/admin/reposterias/{$reposteria->id}")->assertOk();

        foreach (['admin', 'vendedor', 'produccion', 'cliente'] as $rol) {
            Sanctum::actingAs($this->usuario($rol));
            $this->getJson('/api/v1/admin/reposterias')->assertForbidden();
            $this->getJson("/api/v1/admin/reposterias/{$reposteria->id}")->assertForbidden();
        }
    }

    public function test_listing_filters_all_states_and_rejects_invalid_state(): void
    {
        $superadmin = $this->usuario('superadmin');
        $estados = app(ReposteriaEstadoService::class);
        $pendiente = $this->reposteriaPendiente();
        $aprobada = $estados->aprobar($this->reposteriaPendiente(), $superadmin);
        $rechazada = $estados->rechazar($this->reposteriaPendiente(), $superadmin, 'Datos incompletos');
        $suspendida = $estados->suspender($estados->aprobar($this->reposteriaPendiente(), $superadmin), $superadmin, 'Revisión');
        $inactiva = $estados->inactivar($this->reposteriaPendiente(), $superadmin, 'Cierre');
        Sanctum::actingAs($superadmin);
        foreach (compact('pendiente', 'aprobada', 'rechazada', 'suspendida', 'inactiva') as $estado => $reposteria) {
            $this->getJson("/api/v1/admin/reposterias?estado={$estado}&per_page=1")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $reposteria->id);
        }
        $this->getJson('/api/v1/admin/reposterias?estado=inventada')->assertUnprocessable();
    }

    public function test_listing_searches_name_slug_owner_and_paginates_in_descending_order(): void
    {
        $primera = $this->reposteriaPendiente(['nombre' => 'Dulce Aurora']);
        $segunda = $this->reposteriaPendiente(['nombre' => 'Pastelería Luna']);
        $segunda->propietario->forceFill(['name' => 'Propietaria Especial', 'email' => 'especial@example.test'])->save();
        Sanctum::actingAs($this->usuario('superadmin'));
        $this->getJson('/api/v1/admin/reposterias?search=Aurora')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $primera->id);
        $this->getJson('/api/v1/admin/reposterias?search=dulce-aurora')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $primera->id);
        $this->getJson('/api/v1/admin/reposterias?search=especial@example.test')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $segunda->id);
        $this->getJson('/api/v1/admin/reposterias?per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $segunda->id);
    }

    public function test_detail_resource_exposes_metadata_without_owner_credentials(): void
    {
        $superadmin = $this->usuario('superadmin');
        $reposteria = app(ReposteriaEstadoService::class)->aprobar($this->reposteriaPendiente(['nombre' => 'Detalle']), $superadmin);
        Sanctum::actingAs($superadmin);
        $this->getJson("/api/v1/admin/reposterias/{$reposteria->id}")->assertOk()
            ->assertJsonPath('data.estado', 'aprobada')
            ->assertJsonPath('data.propietario.id', $reposteria->propietario_id)
            ->assertJsonPath('data.aprobada_por.id', $superadmin->id)
            ->assertJsonPath('data.motivo_estado', null)
            ->assertJsonMissingPath('data.propietario.password')
            ->assertJsonMissingPath('data.propietario.remember_token')
            ->assertJsonMissingPath('data.propietario.tokens');
        $this->getJson('/api/v1/admin/reposterias/999999')->assertNotFound();
    }

    public function test_superadmin_approves_pending_bakery_with_consistent_metadata(): void
    {
        $superadmin = $this->usuario('superadmin');
        $reposteria = $this->reposteriaPendiente();
        Sanctum::actingAs($superadmin);
        $respuesta = $this->postJson("/api/v1/admin/reposterias/{$reposteria->id}/aprobar")->assertOk()->assertJsonPath('data.estado', 'aprobada')->assertJsonPath('data.aprobada_por.id', $superadmin->id)->assertJsonPath('data.motivo_estado', null);
        $this->assertNotNull($respuesta->json('data.fecha_aprobacion'));
        $this->postJson("/api/v1/admin/reposterias/{$reposteria->id}/aprobar")->assertUnprocessable();
    }

    public function test_reject_suspend_and_inactivate_follow_existing_transitions_and_motives(): void
    {
        $superadmin = $this->usuario('superadmin');
        Sanctum::actingAs($superadmin);
        $rechazada = $this->reposteriaPendiente();
        $this->postJson("/api/v1/admin/reposterias/{$rechazada->id}/rechazar", ['motivo' => '  Información incompleta  '])->assertOk()->assertJsonPath('data.estado', 'rechazada')->assertJsonPath('data.motivo_estado', 'Información incompleta')->assertJsonPath('data.fecha_aprobacion', null);
        $this->postJson("/api/v1/admin/reposterias/{$rechazada->id}/rechazar", ['motivo' => 'Otra'])->assertUnprocessable();

        $aprobada = app(ReposteriaEstadoService::class)->aprobar($this->reposteriaPendiente(), $superadmin);
        $this->postJson("/api/v1/admin/reposterias/{$aprobada->id}/suspender", ['motivo' => 'Temporal'])->assertOk()->assertJsonPath('data.estado', 'suspendida')->assertJsonPath('data.motivo_estado', 'Temporal');
        $pendiente = $this->reposteriaPendiente();
        $this->postJson("/api/v1/admin/reposterias/{$pendiente->id}/suspender", ['motivo' => 'No'])->assertUnprocessable();
        $this->postJson("/api/v1/admin/reposterias/{$pendiente->id}/inactivar", ['motivo' => 'Cierre'])->assertOk()->assertJsonPath('data.estado', 'inactiva');
        $this->postJson("/api/v1/admin/reposterias/{$pendiente->id}/inactivar")->assertUnprocessable();
    }

    public function test_required_motives_and_non_superadmin_transitions_are_rejected(): void
    {
        $pendiente = $this->reposteriaPendiente();
        $superadmin = $this->usuario('superadmin');
        Sanctum::actingAs($superadmin);
        $this->postJson("/api/v1/admin/reposterias/{$pendiente->id}/rechazar", ['motivo' => '   '])->assertUnprocessable();
        $aprobada = app(ReposteriaEstadoService::class)->aprobar($pendiente, $superadmin);
        $this->postJson("/api/v1/admin/reposterias/{$aprobada->id}/suspender", [])->assertUnprocessable();

        foreach (['admin', 'vendedor', 'produccion', 'cliente'] as $rol) {
            $reposteria = $this->reposteriaPendiente();
            Sanctum::actingAs($this->usuario($rol));
            $this->postJson("/api/v1/admin/reposterias/{$reposteria->id}/aprobar")->assertForbidden();
            $this->postJson("/api/v1/admin/reposterias/{$reposteria->id}/rechazar", ['motivo' => 'No'])->assertForbidden();
            $this->postJson("/api/v1/admin/reposterias/{$reposteria->id}/inactivar")->assertForbidden();
        }
    }

    public function test_no_generic_state_patch_delete_or_reactivation_routes_exist(): void
    {
        $reposteria = $this->reposteriaPendiente();
        Sanctum::actingAs($this->usuario('superadmin'));
        $this->patchJson("/api/v1/admin/reposterias/{$reposteria->id}", ['estado' => 'aprobada'])->assertMethodNotAllowed();
        $this->deleteJson("/api/v1/admin/reposterias/{$reposteria->id}")->assertMethodNotAllowed();
        $this->postJson("/api/v1/admin/reposterias/{$reposteria->id}/reactivar")->assertNotFound();
        $this->assertSame(ReposteriaEstado::Pendiente, $reposteria->fresh()->estado);
    }

    public function test_suspended_bakery_remains_blocked_for_operational_apis(): void
    {
        $superadmin = $this->usuario('superadmin');
        $reposteria = app(ReposteriaEstadoService::class)->aprobar($this->reposteriaPendiente(), $superadmin);
        $admin = $reposteria->propietario;
        app(ReposteriaEstadoService::class)->suspender($reposteria, $superadmin, 'Temporal');
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/productos")->assertForbidden();
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/empleados")->assertForbidden();
    }

    private function reposteriaPendiente(array $atributos = []): Reposteria
    {
        return Reposteria::factory()->for($this->usuario('admin'), 'propietario')->create($atributos);
    }

    private function usuario(string $rol, bool $activo = true): User
    {
        return User::factory()->for(Role::query()->where('nombre', $rol)->firstOrFail())->create(['activo' => $activo]);
    }
}
