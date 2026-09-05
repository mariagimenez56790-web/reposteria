<?php

namespace Tests\Feature;

use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ReposteriaEstadoService;
use App\Services\ReposteriaUsuarioService;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReposteriaMembershipTest extends TestCase
{
    use RefreshDatabase;

    private ReposteriaUsuarioService $membresias;

    private ReposteriaEstadoService $estados;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->membresias = app(ReposteriaUsuarioService::class);
        $this->estados = app(ReposteriaEstadoService::class);
    }

    public function test_membership_table_has_the_expected_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('reposteria_user', [
            'id', 'reposteria_id', 'user_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_owner_is_automatically_added_as_an_operational_member(): void
    {
        $propietario = $this->usuarioConRol('admin');
        $reposteria = Reposteria::factory()->for($propietario, 'propietario')->create();

        $this->assertTrue($propietario->perteneceAReposteria($reposteria));
        $this->assertTrue($reposteria->usuarios->first()->is($propietario));
        $this->assertDatabaseCount('reposteria_user', 1);
        $this->assertFalse($propietario->puedeOperarEnReposteria($reposteria));
    }

    public function test_internal_roles_can_be_associated_to_an_approved_reposteria(): void
    {
        [$reposteria, $propietario] = $this->reposteriaAprobada();

        foreach (['admin', 'vendedor', 'produccion'] as $rol) {
            $usuario = $this->usuarioConRol($rol);
            $this->membresias->asociar($propietario, $reposteria, $usuario);

            $this->assertTrue($usuario->perteneceAReposteria($reposteria));
            $this->assertTrue($usuario->puedeOperarEnReposteria($reposteria));
        }

        $this->assertCount(4, $reposteria->usuarios()->get());
    }

    public function test_superadmin_and_cliente_cannot_be_added_as_workers(): void
    {
        [$reposteria, $propietario] = $this->reposteriaAprobada();

        foreach (['superadmin', 'cliente'] as $rol) {
            try {
                $this->membresias->asociar($propietario, $reposteria, $this->usuarioConRol($rol));
                $this->fail("El rol {$rol} no debió ser asociado como trabajador.");
            } catch (DomainException) {
                $this->assertDatabaseCount('reposteria_user', 1);
            }
        }
    }

    public function test_inactive_user_cannot_be_associated_or_operate(): void
    {
        [$reposteria, $propietario] = $this->reposteriaAprobada();
        $usuario = $this->usuarioConRol('vendedor', false);

        try {
            $this->membresias->asociar($propietario, $reposteria, $usuario);
            $this->fail('Un usuario inactivo no debió ser asociado.');
        } catch (DomainException) {
            $this->assertFalse($usuario->perteneceAReposteria($reposteria));
            $this->assertFalse($usuario->puedeOperarEnReposteria($reposteria));
        }
    }

    public function test_membership_is_isolated_between_reposterias(): void
    {
        [$reposteriaA, $propietarioA] = $this->reposteriaAprobada();
        [$reposteriaB] = $this->reposteriaAprobada();
        $vendedor = $this->usuarioConRol('vendedor');
        $this->membresias->asociar($propietarioA, $reposteriaA, $vendedor);

        $this->assertTrue($vendedor->perteneceAReposteria($reposteriaA));
        $this->assertTrue($this->membresias->puedeOperar($vendedor, $reposteriaA));
        $this->assertFalse($vendedor->perteneceAReposteria($reposteriaB));
        $this->assertFalse($this->membresias->puedeOperar($vendedor, $reposteriaB));
    }

    public function test_service_rejects_a_duplicate_membership(): void
    {
        [$reposteria, $propietario] = $this->reposteriaAprobada();
        $vendedor = $this->usuarioConRol('vendedor');
        $this->membresias->asociar($propietario, $reposteria, $vendedor);

        $this->expectException(DomainException::class);

        $this->membresias->asociar($propietario, $reposteria, $vendedor);
    }

    public function test_database_unique_constraint_rejects_a_duplicate_membership(): void
    {
        [$reposteria, $propietario] = $this->reposteriaAprobada();

        $this->expectException(QueryException::class);

        DB::table('reposteria_user')->insert([
            'reposteria_id' => $reposteria->id,
            'user_id' => $propietario->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_worker_can_be_removed_without_deleting_the_user(): void
    {
        [$reposteria, $propietario] = $this->reposteriaAprobada();
        $vendedor = $this->usuarioConRol('vendedor');
        $this->membresias->asociar($propietario, $reposteria, $vendedor);

        $this->membresias->retirar($propietario, $reposteria, $vendedor);

        $this->assertFalse($vendedor->perteneceAReposteria($reposteria));
        $this->assertNotNull(User::query()->find($vendedor->id));
    }

    public function test_owner_cannot_be_removed_from_membership(): void
    {
        [$reposteria, $propietario] = $this->reposteriaAprobada();

        $this->expectException(DomainException::class);

        $this->membresias->retirar($propietario, $reposteria, $propietario);
    }

    public function test_admin_from_another_reposteria_cannot_manage_memberships(): void
    {
        [$reposteria] = $this->reposteriaAprobada();
        [, $otroAdmin] = $this->reposteriaAprobada();

        $this->expectException(AuthorizationException::class);

        $this->membresias->asociar($otroAdmin, $reposteria, $this->usuarioConRol('vendedor'));
    }

    public function test_superadmin_has_global_access_without_being_an_employee(): void
    {
        [$reposteria] = $this->reposteriaAprobada();
        $superadmin = $this->usuarioConRol('superadmin');

        $this->assertFalse($superadmin->perteneceAReposteria($reposteria));
        $this->assertTrue($superadmin->puedeAccederAReposteria($reposteria));
    }

    public function test_soft_delete_preserves_pivot_memberships_but_blocks_operations(): void
    {
        [$reposteria, $propietario] = $this->reposteriaAprobada();
        $reposteria->delete();

        $this->assertDatabaseHas('reposteria_user', [
            'reposteria_id' => $reposteria->id,
            'user_id' => $propietario->id,
        ]);
        $this->assertFalse($propietario->puedeOperarEnReposteria($reposteria));
    }

    private function usuarioConRol(string $rol, bool $activo = true): User
    {
        return User::factory()
            ->for(Role::query()->where('nombre', $rol)->firstOrFail())
            ->create(['activo' => $activo]);
    }

    /**
     * @return array{Reposteria, User}
     */
    private function reposteriaAprobada(): array
    {
        $propietario = $this->usuarioConRol('admin');
        $reposteria = Reposteria::factory()->for($propietario, 'propietario')->create();
        $superadmin = $this->usuarioConRol('superadmin');

        return [$this->estados->aprobar($reposteria, $superadmin), $propietario];
    }
}
