<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ClienteService;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClienteManagementTest extends TestCase
{
    use RefreshDatabase;

    private ClienteService $clientes;

    private ReposteriaEstadoService $estados;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->clientes = app(ClienteService::class);
        $this->estados = app(ReposteriaEstadoService::class);
    }

    public function test_table_and_relationships_exist_and_new_client_is_active(): void
    {
        $this->assertTrue(Schema::hasColumns('clientes', ['id', 'reposteria_id', 'nombre', 'telefono', 'email', 'direccion', 'notas', 'activo', 'created_at', 'updated_at', 'deleted_at']));
        [$r, $admin] = $this->reposteriaAprobada();
        $cliente = $this->clientes->crear($admin, $r, $this->datos());
        $this->assertTrue($cliente->activo);
        $this->assertTrue($cliente->reposteria->is($r));
        $this->assertTrue($r->clientes->first()->is($cliente));
    }

    public function test_name_is_required_but_duplicates_are_allowed_even_across_reposterias(): void
    {
        [$a, $adminA] = $this->reposteriaAprobada();
        [$b, $adminB] = $this->reposteriaAprobada();
        $this->clientes->crear($adminA, $a, $this->datos(['nombre' => 'Juan Pérez']));
        $this->clientes->crear($adminA, $a, $this->datos(['nombre' => 'Juan Pérez']));
        $this->clientes->crear($adminB, $b, $this->datos(['nombre' => 'Juan Pérez']));
        $this->assertDatabaseCount('clientes', 3);

        $this->expectException(ValidationException::class);
        $this->clientes->crear($adminA, $a, $this->datos(['nombre' => '']));
    }

    public function test_phone_is_a_string_and_email_is_optional_normalized_and_validated(): void
    {
        [$r, $admin] = $this->reposteriaAprobada();
        $cliente = $this->clientes->crear($admin, $r, $this->datos(['telefono' => '+591 070-123', 'email' => ' ANA@EXAMPLE.COM ']));
        $this->assertSame('+591 070-123', $cliente->telefono);
        $this->assertSame('ana@example.com', $cliente->email);
        $sinContacto = $this->clientes->crear($admin, $r, $this->datos(['telefono' => null, 'email' => null]));
        $this->assertNull($sinContacto->telefono);
        $this->assertNull($sinContacto->email);

        $this->expectException(ValidationException::class);
        $this->clientes->crear($admin, $r, $this->datos(['email' => 'correo-invalido']));
    }

    public function test_admin_and_vendor_can_create_update_and_list_only_their_reposteria(): void
    {
        [$a, $adminA] = $this->reposteriaAprobada();
        [$b, $adminB] = $this->reposteriaAprobada();
        $vendedor = $this->miembro($a, 'vendedor');
        $clienteA = $this->clientes->crear($vendedor, $a, $this->datos(['nombre' => 'Cliente A']));
        $this->clientes->crear($adminB, $b, $this->datos(['nombre' => 'Cliente B']));
        $clienteA = $this->clientes->actualizar($vendedor, $clienteA, $this->datos(['nombre' => 'Cliente A Editado']));
        $this->assertSame('Cliente A Editado', $clienteA->nombre);
        $this->assertCount(1, $this->clientes->listar($adminA, $a));
        $this->assertSame('Cliente A Editado', $this->clientes->listar($vendedor, $a)->first()->nombre);
    }

    public function test_admin_a_cannot_update_or_list_clients_from_b(): void
    {
        [$a, $adminA] = $this->reposteriaAprobada();
        [$b, $adminB] = $this->reposteriaAprobada();
        $clienteB = $this->clientes->crear($adminB, $b, $this->datos());
        $this->assertCount(0, Cliente::deReposteria($a)->get());
        $this->expectException(AuthorizationException::class);
        $this->clientes->actualizar($adminA, $clienteB, $this->datos());
    }

    public function test_vendor_cannot_delete_or_change_active_state(): void
    {
        [$r, $admin] = $this->reposteriaAprobada();
        $vendedor = $this->miembro($r, 'vendedor');
        $cliente = $this->clientes->crear($admin, $r, $this->datos());
        try {
            $this->clientes->establecerActivo($vendedor, $cliente, false);
            $this->fail('El vendedor no debió desactivar clientes.');
        } catch (AuthorizationException) {
            $this->assertTrue($cliente->fresh()->activo);
        }
        $this->expectException(AuthorizationException::class);
        $this->clientes->eliminar($vendedor, $cliente);
    }

    public function test_production_client_role_and_inactive_user_are_blocked(): void
    {
        [$r] = $this->reposteriaAprobada();
        $inactivo = $this->miembro($r, 'admin');
        $inactivo->update(['activo' => false]);
        foreach ([$this->miembro($r, 'produccion'), $this->usuario('cliente'), $inactivo] as $actor) {
            try {
                $this->clientes->crear($actor, $r, $this->datos());
                $this->fail('El usuario no debió administrar clientes.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_superadmin_can_manage_clients_and_admin_can_toggle_and_soft_delete(): void
    {
        [$r, $admin] = $this->reposteriaAprobada();
        $cliente = $this->clientes->crear($this->usuario('superadmin'), $r, $this->datos());
        $this->assertFalse($this->clientes->establecerActivo($admin, $cliente, false)->activo);
        $this->assertTrue($this->clientes->establecerActivo($admin, $cliente, true)->activo);
        $this->clientes->eliminar($admin, $cliente);
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
        $this->assertNotNull(Cliente::withTrashed()->find($cliente->id));
    }

    public function test_reposteria_id_cannot_be_moved_by_mass_input(): void
    {
        [$a, $adminA] = $this->reposteriaAprobada();
        [$b] = $this->reposteriaAprobada();
        $cliente = $this->clientes->crear($adminA, $a, $this->datos(['reposteria_id' => $b->id]));
        $this->assertSame($a->id, $cliente->reposteria_id);
        $cliente = $this->clientes->actualizar($adminA, $cliente, $this->datos(['reposteria_id' => $b->id]));
        $this->assertSame($a->id, $cliente->reposteria_id);
    }

    private function reposteriaAprobada(): array
    {
        $admin = $this->usuario('admin');
        $r = Reposteria::factory()->for($admin, 'propietario')->create();

        return [$this->estados->aprobar($r, $this->usuario('superadmin')), $admin];
    }

    private function miembro(Reposteria $r, string $rol): User
    {
        $user = $this->usuario($rol);
        $r->usuarios()->attach($user);

        return $user;
    }

    private function usuario(string $rol): User
    {
        return User::factory()->for(Role::where('nombre', $rol)->firstOrFail())->create();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge(['nombre' => 'Ana López', 'telefono' => '070707070', 'email' => 'ana@example.com', 'direccion' => 'Calle 1', 'notas' => null], $cambios);
    }
}
