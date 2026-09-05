<?php

namespace Tests\Feature;

use App\Enums\PedidoEstado;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PedidoManagementTest extends TestCase
{
    use RefreshDatabase;

    private PedidoService $pedidos;

    private ReposteriaEstadoService $estados;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->pedidos = app(PedidoService::class);
        $this->estados = app(ReposteriaEstadoService::class);
    }

    public function test_tables_and_order_relationships_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('pedidos', ['id', 'reposteria_id', 'cliente_id', 'estado', 'fecha_pedido', 'fecha_entrega', 'observaciones', 'total', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('pedido_detalles', ['id', 'pedido_id', 'producto_id', 'producto_variante_id', 'nombre_producto', 'nombre_variante', 'cantidad', 'precio_unitario', 'subtotal']));
        [$r, $admin] = $this->contexto();
        $cliente = Cliente::factory()->for($r)->create();
        $producto = Producto::factory()->for($r)->create(['precio' => '50.00']);
        $pedido = $this->pedidos->crear($admin, $r, ['cliente_id' => $cliente->id], [['producto_id' => $producto->id, 'cantidad' => 2]]);
        $this->assertSame(PedidoEstado::Pendiente, $pedido->estado);
        $this->assertTrue($pedido->reposteria->is($r));
        $this->assertTrue($pedido->cliente->is($cliente));
        $this->assertCount(1, $pedido->detalles);
    }

    public function test_order_without_client_and_multiple_details_calculates_historical_amounts(): void
    {
        [$r, $admin] = $this->contexto();
        $p1 = Producto::factory()->for($r)->create(['nombre' => 'Torta', 'precio' => '80.50']);
        $p2 = Producto::factory()->for($r)->create(['nombre' => 'Cupcakes', 'precio' => '10.00']);
        $pedido = $this->pedidos->crear($admin, $r, [], [['producto_id' => $p1->id, 'cantidad' => 2, 'precio_unitario' => 1], ['producto_id' => $p2->id, 'cantidad' => 3, 'subtotal' => 1]]);
        $this->assertNull($pedido->cliente_id);
        $this->assertSame('161.00', $pedido->detalles[0]->subtotal);
        $this->assertSame('191.00', $pedido->total);
        $p1->update(['nombre' => 'Nombre nuevo', 'precio' => '200.00']);
        $this->assertSame('Torta', $pedido->detalles[0]->fresh()->nombre_producto);
        $this->assertSame('80.50', $pedido->detalles[0]->fresh()->precio_unitario);
    }

    public function test_variant_price_name_and_product_are_validated(): void
    {
        [$r, $admin] = $this->contexto();
        $producto = Producto::factory()->for($r)->create(['precio' => 50]);
        $variante = ProductoVariante::factory()->for($producto)->create(['nombre' => 'Grande', 'precio' => '120.00']);
        $pedido = $this->pedidos->crear($admin, $r, [], [['producto_id' => $producto->id, 'producto_variante_id' => $variante->id, 'cantidad' => 2]]);
        $detalle = $pedido->detalles[0];
        $this->assertSame('Grande', $detalle->nombre_variante);
        $this->assertSame('120.00', $detalle->precio_unitario);
        $this->assertSame('240.00', $pedido->total);
        $otro = Producto::factory()->for($r)->create();
        $this->expectException(ValidationException::class);
        $this->pedidos->crear($admin, $r, [], [['producto_id' => $otro->id, 'producto_variante_id' => $variante->id, 'cantidad' => 1]]);
    }

    public function test_cross_tenant_client_product_and_invalid_quantity_are_rejected_transactionally(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $clienteB = Cliente::factory()->for($b)->create();
        $productoB = Producto::factory()->for($b)->create();
        foreach ([[['cliente_id' => $clienteB->id], [['producto_id' => $productoB->id, 'cantidad' => 1]]], [[], [['producto_id' => $productoB->id, 'cantidad' => 1]]], [[], [['producto_id' => Producto::factory()->for($a)->create()->id, 'cantidad' => 0]]]] as [$cabecera, $lineas]) {
            try {
                $this->pedidos->crear($adminA, $a, $cabecera, $lineas);
                $this->fail('Datos cruzados o inválidos aceptados.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('pedidos', 0);
            }
        }
    }

    public function test_stock_is_validated_but_not_deducted_and_uncontrolled_stock_does_not_block(): void
    {
        [$r, $admin] = $this->contexto();
        $controlado = Producto::factory()->for($r)->create(['maneja_stock' => true, 'stock' => 1]);
        try {
            $this->pedidos->crear($admin, $r, [], [['producto_id' => $controlado->id, 'cantidad' => 2]]);
            $this->fail('Stock insuficiente aceptado.');
        } catch (ValidationException) {
            $this->assertSame(1, $controlado->fresh()->stock);
        }
        $libre = Producto::factory()->for($r)->create(['maneja_stock' => false, 'stock' => 0]);
        $this->pedidos->crear($admin, $r, [], [['producto_id' => $libre->id, 'cantidad' => 100]]);
        $this->assertSame(0, $libre->fresh()->stock);
    }

    public function test_state_permissions_and_terminal_states(): void
    {
        [$r, $admin] = $this->contexto();
        $vendedor = $this->miembro($r, 'vendedor');
        $produccion = $this->miembro($r, 'produccion');
        $producto = Producto::factory()->for($r)->create();
        $pedido = $this->pedidos->crear($vendedor, $r, [], [['producto_id' => $producto->id, 'cantidad' => 1]]);
        $pedido = $this->pedidos->cambiarEstado($vendedor, $pedido, PedidoEstado::Confirmado);
        try {
            $this->pedidos->cambiarEstado($vendedor, $pedido, PedidoEstado::EnProduccion);
            $this->fail('Vendedor avanzó producción.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $pedido = $this->pedidos->cambiarEstado($produccion, $pedido, PedidoEstado::EnProduccion);
        $pedido = $this->pedidos->cambiarEstado($produccion, $pedido, PedidoEstado::Listo);
        $pedido = $this->pedidos->cambiarEstado($admin, $pedido, PedidoEstado::Entregado);
        $this->expectException(DomainException::class);
        $this->pedidos->cambiarEstado($admin, $pedido, PedidoEstado::Cancelado);
    }

    public function test_confirmed_order_blocks_details_and_empty_order_cannot_confirm(): void
    {
        [$r, $admin] = $this->contexto();
        $producto = Producto::factory()->for($r)->create();
        $pedido = $this->pedidos->crear($admin, $r, [], [['producto_id' => $producto->id, 'cantidad' => 1]]);
        $pedido = $this->pedidos->cambiarEstado($admin, $pedido, PedidoEstado::Confirmado);
        try {
            $this->pedidos->eliminarDetalle($admin, $pedido->detalles()->first());
            $this->fail('Detalle confirmado eliminado.');
        } catch (DomainException) {
            $this->assertDatabaseCount('pedido_detalles', 1);
        }
        $vacio = Pedido::factory()->for($r)->create();
        $this->expectException(DomainException::class);
        $this->pedidos->cambiarEstado($admin, $vacio, PedidoEstado::Confirmado);
    }

    public function test_roles_tenant_isolation_and_soft_delete_policy(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b, $adminB] = $this->contexto();
        $productoB = Producto::factory()->for($b)->create();
        $pedidoB = $this->pedidos->crear($adminB, $b, [], [['producto_id' => $productoB->id, 'cantidad' => 1]]);
        foreach ([$adminA, $this->usuario('cliente'), $this->usuario('admin', false)] as $actor) {
            try {
                $this->pedidos->actualizar($actor, $pedidoB, []);
                $this->fail('Acceso indebido.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $this->pedidos->eliminar($this->usuario('superadmin'), $pedidoB);
        $this->assertSoftDeleted('pedidos', ['id' => $pedidoB->id]);
        $this->assertDatabaseCount('pedido_detalles', 1);
    }

    private function contexto(): array
    {
        $admin = $this->usuario('admin');
        $r = Reposteria::factory()->for($admin, 'propietario')->create();

        return [$this->estados->aprobar($r, $this->usuario('superadmin')), $admin];
    }

    private function miembro(Reposteria $r, string $rol): User
    {
        $u = $this->usuario($rol);
        $r->usuarios()->attach($u);

        return $u;
    }

    private function usuario(string $rol, bool $activo = true): User
    {
        return User::factory()->for(Role::where('nombre', $rol)->firstOrFail())->create(['activo' => $activo]);
    }
}
