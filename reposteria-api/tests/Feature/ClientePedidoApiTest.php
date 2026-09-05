<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Promocion;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientePedidoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_client_and_order_routes_require_authentication_and_active_user(): void
    {
        foreach (['/api/v1/reposterias/1/clientes', '/api/v1/reposterias/1/pedidos'] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
        $inactivo = $this->usuario('admin', false);
        $reposteria = Reposteria::factory()->for($this->usuario('admin'), 'propietario')->create();
        Sanctum::actingAs($inactivo);
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/clientes")->assertForbidden();
    }

    public function test_admin_and_vendor_list_search_and_paginate_only_their_clients(): void
    {
        foreach (['admin', 'vendedor'] as $rol) {
            [$reposteria, $actor] = $this->contexto($rol);
            Cliente::factory()->for($reposteria)->create(['nombre' => 'Ana Dulce', 'telefono' => '7001']);
            Cliente::factory()->for($reposteria)->create(['nombre' => 'Otra']);
            Cliente::factory()->for($this->contexto()[0])->create(['nombre' => 'Ana Ajena']);
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/clientes?search=Ana&per_page=1")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Ana Dulce')->assertJsonPath('meta.total', 1)->assertJsonMissingPath('data.0.reposteria_id');
        }
    }

    public function test_client_detail_creation_and_partial_update_are_tenant_safe(): void
    {
        [$reposteria, $vendedor] = $this->contexto('vendedor');
        Sanctum::actingAs($vendedor);
        $creada = $this->postJson("/api/v1/reposterias/{$reposteria->id}/clientes", ['nombre' => 'Luz', 'telefono' => '700', 'email' => 'LUZ@EXAMPLE.COM', 'reposteria_id' => 999, 'activo' => false])->assertCreated()->assertJsonPath('data.activo', true)->assertJsonMissingPath('data.reposteria_id');
        $id = $creada->json('data.id');
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/clientes/{$id}")->assertOk()->assertJsonPath('data.email', 'luz@example.com');
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/clientes/{$id}", ['telefono' => '711', 'activo' => false])->assertOk()->assertJsonPath('data.telefono', '711')->assertJsonPath('data.activo', true);
        $ajeno = Cliente::factory()->for($this->contexto()[0])->create();
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/clientes/{$ajeno->id}")->assertNotFound();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/clientes/{$ajeno->id}", ['nombre' => 'Hack'])->assertNotFound();
    }

    public function test_production_and_client_role_cannot_manage_clients(): void
    {
        [$reposteria] = $this->contexto();
        foreach ([$this->miembro($reposteria, 'produccion'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/clientes")->assertForbidden();
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/clientes", ['nombre' => 'No'])->assertForbidden();
        }
    }

    public function test_order_listing_supports_filters_pagination_and_production_reading(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $cliente = Cliente::factory()->for($reposteria)->create();
        Pedido::factory()->for($reposteria)->for($cliente)->create(['estado' => 'pendiente', 'fecha_pedido' => now()->subDay()]);
        Pedido::factory()->for($reposteria)->create(['estado' => 'cancelado', 'fecha_pedido' => now()]);
        $produccion = $this->miembro($reposteria, 'produccion');
        Sanctum::actingAs($produccion);
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/pedidos?estado=pendiente&cliente_id={$cliente->id}&fecha_desde=".now()->subDays(2)->toDateString().'&fecha_hasta='.now()->toDateString().'&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->assertTrue($admin->perteneceAReposteria($reposteria));
    }

    public function test_order_creation_calculates_promotional_snapshot_and_ignores_external_money(): void
    {
        [$reposteria, $vendedor] = $this->contexto('vendedor');
        $producto = Producto::factory()->for($reposteria)->create(['nombre' => 'Torta', 'precio' => '100.00']);
        $variante = ProductoVariante::factory()->for($producto)->create(['nombre' => 'Grande', 'precio' => '120.00']);
        $general = Promocion::factory()->for($reposteria)->montoFijo('30.00')->create();
        $especifica = Promocion::factory()->for($reposteria)->porcentaje('10.00')->create();
        $general->productos()->attach($producto);
        $especifica->variantes()->attach($variante);
        Sanctum::actingAs($vendedor);
        $respuesta = $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos", ['cliente_id' => null, 'fecha_entrega' => now()->addDay()->toISOString(), 'total' => '0.01', 'estado' => 'entregado', 'detalles' => [['producto_id' => $producto->id, 'producto_variante_id' => $variante->id, 'cantidad' => 2, 'precio_unitario' => '0.01']]])->assertCreated();
        $respuesta->assertJsonPath('data.estado', 'pendiente')->assertJsonPath('data.total', '216.00')->assertJsonPath('data.detalles.0.precio_unitario', '108.00')->assertJsonPath('data.detalles.0.subtotal', '216.00')->assertJsonPath('data.detalles.0.nombre_producto', 'Torta')->assertJsonPath('data.detalles.0.nombre_variante', 'Grande');
        $producto->forceFill(['nombre' => 'Nuevo', 'precio' => '200.00'])->save();
        $this->assertSame('108.00', Pedido::findOrFail($respuesta->json('data.id'))->detalles()->firstOrFail()->precio_unitario);
    }

    public function test_invalid_client_product_variant_and_quantity_roll_back_creation(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $clienteB = Cliente::factory()->for($b)->create();
        $productoA = Producto::factory()->for($a)->create();
        $productoB = Producto::factory()->for($b)->create();
        $varianteB = ProductoVariante::factory()->for($productoB)->create();
        Sanctum::actingAs($adminA);
        foreach ([['cliente_id' => $clienteB->id, 'detalles' => [['producto_id' => $productoA->id, 'cantidad' => 1]]], ['detalles' => [['producto_id' => $productoB->id, 'cantidad' => 1]]], ['detalles' => [['producto_id' => $productoA->id, 'producto_variante_id' => $varianteB->id, 'cantidad' => 1]]], ['detalles' => [['producto_id' => $productoA->id, 'cantidad' => 0]]]] as $datos) {
            $this->postJson("/api/v1/reposterias/{$a->id}/pedidos", $datos)->assertUnprocessable();
        }
        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_order_detail_uses_snapshots_and_cross_tenant_ids_return_not_found(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $pedidoA = Pedido::factory()->for($a)->create();
        $pedidoB = Pedido::factory()->for($b)->create();
        $producto = Producto::factory()->for($a)->create();
        $detalle = new PedidoDetalle;
        $detalle->forceFill(['pedido_id' => $pedidoA->id, 'producto_id' => $producto->id, 'producto_variante_id' => null, 'nombre_producto' => 'Histórico', 'nombre_variante' => 'Viejo', 'cantidad' => 2, 'precio_unitario' => '5.00', 'subtotal' => '10.00'])->save();
        $producto->forceFill(['nombre' => 'Actual', 'precio' => '99.00'])->save();
        Sanctum::actingAs($adminA);
        $this->getJson("/api/v1/reposterias/{$a->id}/pedidos/{$pedidoA->id}")->assertOk()->assertJsonPath('data.detalles.0.nombre_producto', 'Histórico')->assertJsonPath('data.detalles.0.precio_unitario', '5.00');
        $this->getJson("/api/v1/reposterias/{$a->id}/pedidos/{$pedidoB->id}")->assertNotFound();
    }

    public function test_pending_order_header_and_details_can_be_edited_with_total_recalculation(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '10.00']);
        Sanctum::actingAs($admin);
        $pedido = $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}", ['observaciones' => 'Nueva', 'estado' => 'entregado'])->assertOk()->assertJsonPath('data.observaciones', 'Nueva')->assertJsonPath('data.estado', 'pendiente');
        $nuevo = $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/detalles", ['producto_id' => $producto->id, 'cantidad' => 2])->assertCreated()->json('data');
        $this->assertSame('30.00', Pedido::find($pedido['id'])->total);
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/detalles/{$nuevo['id']}", ['cantidad' => 3, 'precio_unitario' => 1])->assertOk()->assertJsonPath('data.subtotal', '30.00');
        $this->assertSame('40.00', Pedido::find($pedido['id'])->total);
        $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/detalles/{$nuevo['id']}")->assertNoContent();
        $this->assertSame('10.00', Pedido::find($pedido['id'])->total);
    }

    public function test_detail_from_another_order_and_confirmed_edits_are_blocked(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => 10]);
        Sanctum::actingAs($admin);
        $uno = $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
        $dos = $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$uno['id']}/detalles/{$dos['detalles'][0]['id']}", ['cantidad' => 2])->assertNotFound();
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$uno['id']}/estado", ['estado' => 'confirmado'])->assertOk();
        $this->patchJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$uno['id']}/detalles/{$uno['detalles'][0]['id']}", ['cantidad' => 2])->assertUnprocessable();
    }

    public function test_state_flow_enforces_vendor_and_production_permissions_without_creating_sale_or_stock_movement(): void
    {
        [$reposteria, $vendedor] = $this->contexto('vendedor');
        $produccion = $this->miembro($reposteria, 'produccion');
        $producto = Producto::factory()->for($reposteria)->create(['precio' => 10, 'maneja_stock' => true, 'stock' => 5]);
        Sanctum::actingAs($vendedor);
        $pedido = $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/estado", ['estado' => 'confirmado'])->assertOk();
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/estado", ['estado' => 'en_produccion'])->assertForbidden();
        Sanctum::actingAs($produccion);
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/estado", ['estado' => 'en_produccion'])->assertOk();
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/estado", ['estado' => 'listo'])->assertOk();
        Sanctum::actingAs($vendedor);
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/estado", ['estado' => 'entregado'])->assertForbidden();
        Sanctum::actingAs($this->miembro($reposteria, 'admin'));
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/estado", ['estado' => 'entregado'])->assertOk();
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame(5, $producto->fresh()->stock);
    }

    public function test_production_and_client_role_cannot_create_or_edit_orders(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '10.00']);
        Sanctum::actingAs($admin);
        $pedido = $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos", [
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertCreated()->json('data');

        foreach ([$this->miembro($reposteria, 'produccion'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos", [
                'detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            ])->assertForbidden();
            $this->patchJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}", [
                'observaciones' => 'No autorizado',
            ])->assertForbidden();
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/pedidos/{$pedido['id']}/detalles", [
                'producto_id' => $producto->id,
                'cantidad' => 1,
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('pedidos', 1);
        $this->assertDatabaseCount('pedido_detalles', 1);
    }

    public function test_order_urls_enforce_tenant_isolation_and_superadmin_has_global_access(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b, $adminB] = $this->contexto();
        $productoB = Producto::factory()->for($b)->create(['precio' => '12.00']);
        Sanctum::actingAs($adminB);
        $pedidoB = $this->postJson("/api/v1/reposterias/{$b->id}/pedidos", [
            'detalles' => [['producto_id' => $productoB->id, 'cantidad' => 1]],
        ])->assertCreated()->json('data');

        Sanctum::actingAs($adminA);
        $this->getJson("/api/v1/reposterias/{$a->id}/pedidos/{$pedidoB['id']}")->assertNotFound();
        $this->patchJson("/api/v1/reposterias/{$a->id}/pedidos/{$pedidoB['id']}", ['observaciones' => 'Hack'])->assertNotFound();
        $this->postJson("/api/v1/reposterias/{$a->id}/pedidos/{$pedidoB['id']}/estado", ['estado' => 'confirmado'])->assertNotFound();
        $this->deleteJson("/api/v1/reposterias/{$a->id}/pedidos/{$pedidoB['id']}/detalles/{$pedidoB['detalles'][0]['id']}")->assertNotFound();

        Sanctum::actingAs($this->usuario('superadmin'));
        $this->getJson("/api/v1/reposterias/{$b->id}/pedidos/{$pedidoB['id']}")->assertOk();
        $this->patchJson("/api/v1/reposterias/{$b->id}/pedidos/{$pedidoB['id']}", ['observaciones' => 'Global'])->assertOk()->assertJsonPath('data.observaciones', 'Global');
    }

    private function contexto(string $rol = 'admin'): array
    {
        $usuario = $this->usuario($rol);
        $reposteria = Reposteria::factory()->for($usuario, 'propietario')->create();

        return [app(ReposteriaEstadoService::class)->aprobar($reposteria, $this->usuario('superadmin')), $usuario];
    }

    private function usuario(string $rol, bool $activo = true): User
    {
        return User::factory()->for(Role::query()->where('nombre', $rol)->firstOrFail())->create(['activo' => $activo]);
    }

    private function miembro(Reposteria $reposteria, string $rol): User
    {
        $usuario = $this->usuario($rol);
        $reposteria->usuarios()->attach($usuario);

        return $usuario;
    }
}
