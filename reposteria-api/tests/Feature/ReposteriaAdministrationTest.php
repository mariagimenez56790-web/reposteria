<?php

namespace Tests\Feature;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReposteriaAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private ReposteriaEstadoService $estados;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->estados = app(ReposteriaEstadoService::class);
    }

    public function test_reposterias_table_has_the_expected_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('reposterias', [
            'id', 'propietario_id', 'nombre', 'slug', 'descripcion', 'logo', 'portada',
            'telefono', 'email', 'direccion', 'ciudad', 'estado', 'aprobada_por',
            'fecha_aprobacion', 'motivo_estado', 'created_at', 'updated_at', 'deleted_at',
        ]));
    }

    public function test_it_creates_a_pending_reposteria_owned_by_the_registering_user(): void
    {
        $propietario = $this->usuarioConRol('admin');

        $reposteria = $propietario->reposteriasComoPropietario()->create([
            'nombre' => 'Dulce Encanto',
            'descripcion' => 'Tortas artesanales',
        ]);

        $this->assertSame(ReposteriaEstado::Pendiente, $reposteria->estado);
        $this->assertSame('dulce-encanto', $reposteria->slug);
        $this->assertTrue($reposteria->propietario->is($propietario));
        $this->assertTrue($propietario->reposteriasComoPropietario->first()->is($reposteria));
        $this->assertNull($reposteria->aprobada_por);
        $this->assertNull($reposteria->fecha_aprobacion);
    }

    public function test_slug_collisions_are_resolved_predictably_including_soft_deleted_records(): void
    {
        $propietario = $this->usuarioConRol('admin');
        $primera = $propietario->reposteriasComoPropietario()->create(['nombre' => 'Dulce Encanto']);
        $segunda = $propietario->reposteriasComoPropietario()->create(['nombre' => 'Dulce Encanto']);
        $primera->delete();
        $tercera = $propietario->reposteriasComoPropietario()->create(['nombre' => 'Dulce Encanto']);

        $this->assertSame('dulce-encanto', $primera->slug);
        $this->assertSame('dulce-encanto-2', $segunda->slug);
        $this->assertSame('dulce-encanto-3', $tercera->slug);
    }

    public function test_superadmin_can_approve_and_approval_is_audited(): void
    {
        $superadmin = $this->usuarioConRol('superadmin');
        $reposteria = $this->reposteriaPendiente();

        $aprobada = $this->estados->aprobar($reposteria, $superadmin);

        $this->assertSame(ReposteriaEstado::Aprobada, $aprobada->estado);
        $this->assertSame($superadmin->id, $aprobada->aprobada_por);
        $this->assertNotNull($aprobada->fecha_aprobacion);
        $this->assertNull($aprobada->motivo_estado);
        $this->assertTrue($aprobada->aprobadaPor->is($superadmin));
        $this->assertTrue($superadmin->reposteriasAprobadas->first()->is($aprobada));
    }

    public function test_non_superadmin_roles_cannot_approve(): void
    {
        foreach (['admin', 'vendedor', 'produccion', 'cliente'] as $rol) {
            $reposteria = $this->reposteriaPendiente();

            try {
                $this->estados->aprobar($reposteria, $this->usuarioConRol($rol));
                $this->fail("El rol {$rol} no debió poder aprobar.");
            } catch (AuthorizationException) {
                $this->assertSame(ReposteriaEstado::Pendiente, $reposteria->fresh()->estado);
            }
        }
    }

    public function test_inactive_superadmin_cannot_change_state(): void
    {
        $superadmin = $this->usuarioConRol('superadmin', false);

        $this->expectException(AuthorizationException::class);

        $this->estados->aprobar($this->reposteriaPendiente(), $superadmin);
    }

    public function test_administrative_fields_cannot_be_changed_through_mass_assignment(): void
    {
        $propietario = $this->usuarioConRol('admin');
        $supuestoAprobador = $this->usuarioConRol('superadmin');

        $reposteria = $propietario->reposteriasComoPropietario()->create([
            'nombre' => 'Intento inseguro',
            'estado' => ReposteriaEstado::Aprobada->value,
            'aprobada_por' => $supuestoAprobador->id,
            'fecha_aprobacion' => now(),
            'motivo_estado' => 'Valor arbitrario',
            'slug' => 'slug-impuesto',
        ]);

        $this->assertSame(ReposteriaEstado::Pendiente, $reposteria->estado);
        $this->assertNull($reposteria->aprobada_por);
        $this->assertNull($reposteria->fecha_aprobacion);
        $this->assertNull($reposteria->motivo_estado);
        $this->assertSame('intento-inseguro', $reposteria->slug);

        $reposteria->update(['estado' => ReposteriaEstado::Aprobada->value]);
        $this->assertSame(ReposteriaEstado::Pendiente, $reposteria->fresh()->estado);
    }

    public function test_pending_reposteria_can_be_rejected_with_a_reason(): void
    {
        $rechazada = $this->estados->rechazar(
            $this->reposteriaPendiente(),
            $this->usuarioConRol('superadmin'),
            '  Datos comerciales incompletos.  ',
        );

        $this->assertSame(ReposteriaEstado::Rechazada, $rechazada->estado);
        $this->assertSame('Datos comerciales incompletos.', $rechazada->motivo_estado);
        $this->assertNull($rechazada->aprobada_por);
        $this->assertNull($rechazada->fecha_aprobacion);
    }

    public function test_approved_reposteria_can_be_suspended_with_a_reason(): void
    {
        $superadmin = $this->usuarioConRol('superadmin');
        $aprobada = $this->estados->aprobar($this->reposteriaPendiente(), $superadmin);
        $suspendida = $this->estados->suspender($aprobada, $superadmin, 'Incumplimiento temporal.');

        $this->assertSame(ReposteriaEstado::Suspendida, $suspendida->estado);
        $this->assertSame('Incumplimiento temporal.', $suspendida->motivo_estado);
        $this->assertSame($superadmin->id, $suspendida->aprobada_por);
        $this->assertNotNull($suspendida->fecha_aprobacion);
    }

    public function test_invalid_state_transitions_are_rejected(): void
    {
        $superadmin = $this->usuarioConRol('superadmin');
        $rechazada = $this->estados->rechazar(
            $this->reposteriaPendiente(),
            $superadmin,
            'Información insuficiente.',
        );

        $this->expectException(DomainException::class);

        $this->estados->aprobar($rechazada, $superadmin);
    }

    public function test_superadmin_can_inactivate_a_reposteria(): void
    {
        $inactiva = $this->estados->inactivar(
            $this->reposteriaPendiente(),
            $this->usuarioConRol('superadmin'),
            'Cierre solicitado.',
        );

        $this->assertSame(ReposteriaEstado::Inactiva, $inactiva->estado);
        $this->assertSame('Cierre solicitado.', $inactiva->motivo_estado);
    }

    public function test_soft_delete_hides_the_reposteria_without_removing_it(): void
    {
        $reposteria = $this->reposteriaPendiente();

        $reposteria->delete();

        $this->assertSoftDeleted('reposterias', ['id' => $reposteria->id]);
        $this->assertNull(Reposteria::query()->find($reposteria->id));
        $this->assertNotNull(Reposteria::withTrashed()->find($reposteria->id));
    }

    private function usuarioConRol(string $rol, bool $activo = true): User
    {
        return User::factory()
            ->for(Role::query()->where('nombre', $rol)->firstOrFail())
            ->create(['activo' => $activo]);
    }

    private function reposteriaPendiente(): Reposteria
    {
        return Reposteria::factory()
            ->for($this->usuarioConRol('admin'), 'propietario')
            ->create();
    }
}
