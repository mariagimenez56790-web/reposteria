<?php

namespace Tests\Feature;

use App\Enums\PedidoEstado;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Promocion;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Models\Venta;
use App\Services\PedidoService;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VentaPagoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_sale_and_payment_routes_require_authentication_and_active_user(): void
    {
        $this->getJson('/api/v1/reposterias/1/ventas')->assertUnauthorized();
        $this->postJson('/api/v1/reposterias/1/ventas/1/pagos')->assertUnauthorized();

        [$reposteria] = $this->contexto();
        Sanctum::actingAs($this->usuario('admin', false));
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/ventas")->assertForbidden();
    }

    public function test_admin_and_vendor_list_filter_and_paginate_only_their_sales(): void
    {
        foreach (['admin', 'vendedor'] as $rol) {
            [$reposteria, $actor] = $this->contexto($rol);
            $cliente = Cliente::factory()->for($reposteria)->create();
            $pedido = Pedido::factory()->for($reposteria)->create();
            Venta::factory()->for($reposteria)->for($cliente)->for($pedido)->create([
                'estado' => 'pendiente',
                'fecha_venta' => now()->subDay(),
            ]);
            Venta::factory()->for($reposteria)->create(['estado' => 'anulada', 'fecha_venta' => now()]);
            Venta::factory()->for($this->contexto()[0])->create(['estado' => 'pendiente']);

            Sanctum::actingAs($actor);
            $url = "/api/v1/reposterias/{$reposteria->id}/ventas?estado=pendiente&cliente_id={$cliente->id}&pedido_id={$pedido->id}&fecha_desde=".now()->subDays(2)->toDateString().'&fecha_hasta='.now()->toDateString().'&per_page=1';
            $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.estado', 'pendiente')->assertJsonMissingPath('data.0.reposteria_id');
        }
    }

    public function test_production_client_and_foreign_admin_cannot_read_sales_but_superadmin_can(): void
    {
        [$reposteria] = $this->contexto();
        $venta = Venta::factory()->for($reposteria)->create();
        [, $adminAjeno] = $this->contexto();

        foreach ([$this->miembro($reposteria, 'produccion'), $this->usuario('cliente'), $adminAjeno] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/ventas")->assertForbidden();
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta->id}")->assertForbidden();
        }

        Sanctum::actingAs($this->usuario('superadmin'));
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta->id}")->assertOk();
    }

    public function test_direct_sale_applies_variant_promotion_and_ignores_external_financial_fields(): void
    {
        [$reposteria, $vendedor] = $this->contexto('vendedor');
        $producto = Producto::factory()->for($reposteria)->create(['nombre' => 'Torta', 'precio' => '100.00']);
        $variante = ProductoVariante::factory()->for($producto)->create(['nombre' => 'Grande', 'precio' => '120.00']);
        $general = Promocion::factory()->for($reposteria)->montoFijo('40.00')->create();
        $especifica = Promocion::factory()->for($reposteria)->porcentaje('10.00')->create();
        $general->productos()->attach($producto);
        $especifica->variantes()->attach($variante);

        Sanctum::actingAs($vendedor);
        $respuesta = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas", [
            'descuento' => '6.00',
            'subtotal' => '0.01',
            'total' => '0.01',
            'monto_pagado' => '999.00',
            'saldo' => '0.00',
            'estado' => 'pagada',
            'reposteria_id' => 999,
            'detalles' => [[
                'producto_id' => $producto->id,
                'producto_variante_id' => $variante->id,
                'cantidad' => 2,
                'precio_unitario' => '0.01',
                'subtotal' => '0.02',
            ]],
        ])->assertCreated();

        $respuesta->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.subtotal', '216.00')
            ->assertJsonPath('data.descuento', '6.00')
            ->assertJsonPath('data.total', '210.00')
            ->assertJsonPath('data.monto_pagado', '0.00')
            ->assertJsonPath('data.saldo', '210.00')
            ->assertJsonPath('data.detalles.0.precio_unitario', '108.00');

        $producto->forceFill(['nombre' => 'Nuevo', 'precio' => '999.00'])->save();
        $this->assertSame('108.00', Venta::findOrFail($respuesta->json('data.id'))->detalles()->firstOrFail()->precio_unitario);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_direct_sale_rejects_cross_tenant_inactive_and_invalid_detail_data_transactionally(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $clienteB = Cliente::factory()->for($b)->create();
        $productoA = Producto::factory()->for($a)->create();
        $productoB = Producto::factory()->for($b)->create();
        $inactivo = Producto::factory()->for($a)->create(['activo' => false]);
        $varianteB = ProductoVariante::factory()->for($productoB)->create();
        $varianteInactiva = ProductoVariante::factory()->for($productoA)->create(['activo' => false]);
        Sanctum::actingAs($adminA);

        $casos = [
            ['cliente_id' => $clienteB->id, 'detalles' => [['producto_id' => $productoA->id, 'cantidad' => 1]]],
            ['detalles' => [['producto_id' => $productoB->id, 'cantidad' => 1]]],
            ['detalles' => [['producto_id' => $productoA->id, 'producto_variante_id' => $varianteB->id, 'cantidad' => 1]]],
            ['detalles' => [['producto_id' => $inactivo->id, 'cantidad' => 1]]],
            ['detalles' => [['producto_id' => $productoA->id, 'producto_variante_id' => $varianteInactiva->id, 'cantidad' => 1]]],
            ['detalles' => [['producto_id' => $productoA->id, 'cantidad' => 0]]],
            ['detalles' => [['producto_id' => $productoA->id, 'cantidad' => -1]]],
        ];
        foreach ($casos as $datos) {
            $this->postJson("/api/v1/reposterias/{$a->id}/ventas", $datos)->assertUnprocessable();
        }
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('venta_detalles', 0);
    }

    public function test_discount_boundaries_and_optional_client_are_supported(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '20.00']);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->assertCreated()->assertJsonPath('data.cliente', null)->assertJsonPath('data.total', '20.00');
        foreach (['-0.01', '20.01'] as $descuento) {
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas", ['descuento' => $descuento, 'detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->assertUnprocessable();
        }
    }

    public function test_sale_from_ready_and_delivered_order_copies_snapshots_client_and_does_not_reprice(): void
    {
        foreach ([false, true] as $entregado) {
            [$pedido, $admin, $producto] = $this->pedidoListo();
            if ($entregado) {
                $pedido = app(PedidoService::class)->cambiarEstado($admin, $pedido, PedidoEstado::Entregado);
            }
            $precio = $pedido->detalles()->firstOrFail()->precio_unitario;
            $producto->forceFill(['nombre' => 'Actual', 'precio' => '999.00'])->save();
            Sanctum::actingAs($admin);
            $venta = $this->postJson("/api/v1/reposterias/{$pedido->reposteria_id}/pedidos/{$pedido->id}/venta", ['cliente_id' => 999])->assertCreated()->json('data');
            $this->assertSame($pedido->cliente_id, $venta['cliente']['id']);
            $this->assertSame($precio, $venta['detalles'][0]['precio_unitario']);
            $this->assertSame($pedido->detalles()->first()->nombre_producto, $venta['detalles'][0]['nombre_producto']);
        }
    }

    public function test_sale_from_invalid_foreign_or_already_sold_order_is_rejected(): void
    {
        [$pedido, $admin] = $this->pedidoListo();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/reposterias/{$pedido->reposteria_id}/pedidos/{$pedido->id}/venta")->assertCreated();
        $this->postJson("/api/v1/reposterias/{$pedido->reposteria_id}/pedidos/{$pedido->id}/venta")->assertUnprocessable();

        [$otra, $otroAdmin] = $this->contexto();
        Sanctum::actingAs($otroAdmin);
        $this->postJson("/api/v1/reposterias/{$otra->id}/pedidos/{$pedido->id}/venta")->assertNotFound();

        $confirmado = Pedido::factory()->for($otra)->create(['estado' => 'confirmado']);
        $this->postJson("/api/v1/reposterias/{$otra->id}/pedidos/{$confirmado->id}/venta")->assertUnprocessable();
    }

    public function test_sale_detail_returns_snapshots_active_payments_and_iso_dates(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['nombre' => 'Histórico', 'precio' => '50.00']);
        Sanctum::actingAs($admin);
        $venta = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
        $pago = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/pagos", ['metodo' => 'qr', 'monto' => '10.00', 'referencia' => 'QR-1'])->assertCreated()->json('data');
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}")->assertOk()
            ->assertJsonPath('data.detalles.0.nombre_producto', 'Histórico')
            ->assertJsonPath('data.pagos.0.referencia', 'QR-1')
            ->assertJsonPath('data.monto_pagado', '10.00')
            ->assertJsonPath('data.saldo', '40.00');
        $this->assertInstanceOf(\DateTimeImmutable::class, new \DateTimeImmutable($pago['fecha_pago']));
        $this->assertInstanceOf(\DateTimeImmutable::class, new \DateTimeImmutable($venta['fecha_venta']));
        $this->assertStringEndsWith('Z', $pago['fecha_pago']);
        $this->assertStringEndsWith('Z', $venta['fecha_venta']);
    }

    public function test_admin_and_vendor_register_partial_and_full_payments_with_automatic_state(): void
    {
        foreach (['admin', 'vendedor'] as $rol) {
            [$reposteria, $actor] = $this->contexto($rol);
            $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
            Sanctum::actingAs($actor);
            $venta = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/pagos", ['metodo' => 'efectivo', 'monto' => '40.00'])->assertCreated();
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}")->assertJsonPath('data.estado', 'parcial')->assertJsonPath('data.monto_pagado', '40.00')->assertJsonPath('data.saldo', '60.00');
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/pagos", ['metodo' => 'tarjeta', 'monto' => '60.00'])->assertCreated();
            $this->getJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}")->assertJsonPath('data.estado', 'pagada')->assertJsonPath('data.saldo', '0.00');
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/pagos", ['metodo' => 'otro', 'monto' => '1.00'])->assertUnprocessable();
        }
    }

    public function test_invalid_payment_values_overpayment_and_cross_tenant_sale_are_rejected(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $venta = Venta::factory()->for($reposteria)->create(['total' => '50.00', 'saldo' => '50.00']);
        Sanctum::actingAs($admin);
        foreach ([['metodo' => 'bitcoin', 'monto' => '1.00'], ['metodo' => 'qr', 'monto' => '0.00'], ['metodo' => 'qr', 'monto' => '-1.00'], ['metodo' => 'qr', 'monto' => '50.01']] as $datos) {
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta->id}/pagos", $datos)->assertUnprocessable();
        }
        [$otra] = $this->contexto();
        $this->postJson("/api/v1/reposterias/{$otra->id}/ventas/{$venta->id}/pagos", ['metodo' => 'qr', 'monto' => '1.00'])->assertNotFound();
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_only_admin_and_superadmin_delete_payment_and_totals_are_recalculated(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        Sanctum::actingAs($admin);
        $venta = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
        $pago = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/pagos", ['metodo' => 'transferencia', 'monto' => '70.00'])->json('data');
        Sanctum::actingAs($this->miembro($reposteria, 'vendedor'));
        $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/pagos/{$pago['id']}")->assertForbidden();
        Sanctum::actingAs($this->usuario('superadmin'));
        $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/pagos/{$pago['id']}")->assertNoContent();
        $this->getJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}")->assertJsonPath('data.estado', 'pendiente')->assertJsonPath('data.monto_pagado', '0.00')->assertJsonPath('data.saldo', '100.00')->assertJsonCount(0, 'data.pagos');
    }

    public function test_payment_from_another_sale_cannot_be_deleted(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '10.00']);
        Sanctum::actingAs($admin);
        $uno = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
        $dos = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
        $pago = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$dos['id']}/pagos", ['metodo' => 'otro', 'monto' => '1.00'])->json('data');
        $this->deleteJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$uno['id']}/pagos/{$pago['id']}")->assertNotFound();
    }

    public function test_only_admin_and_superadmin_annul_sale_preserving_history_and_blocking_payments(): void
    {
        foreach (['admin', 'superadmin'] as $rol) {
            [$reposteria, $admin] = $this->contexto();
            $actor = $rol === 'admin' ? $admin : $this->usuario('superadmin');
            $producto = Producto::factory()->for($reposteria)->create(['precio' => '25.00']);
            Sanctum::actingAs($admin);
            $venta = $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas", ['detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]]])->json('data');
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/pagos", ['metodo' => 'efectivo', 'monto' => '5.00'])->assertCreated();
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/anular")->assertOk()->assertJsonPath('data.estado', 'anulada')->assertJsonCount(1, 'data.detalles')->assertJsonCount(1, 'data.pagos');
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta['id']}/pagos", ['metodo' => 'efectivo', 'monto' => '1.00'])->assertUnprocessable();
        }

        [$reposteria, $admin] = $this->contexto();
        $venta = Venta::factory()->for($reposteria)->create();
        foreach ([$this->miembro($reposteria, 'vendedor'), $this->miembro($reposteria, 'produccion'), $this->usuario('cliente')] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/reposterias/{$reposteria->id}/ventas/{$venta->id}/anular")->assertForbidden();
        }
        $this->assertNotNull($venta->fresh());
        $this->assertTrue($admin->perteneceAReposteria($reposteria));
    }

    private function contexto(string $rol = 'admin'): array
    {
        $usuario = $this->usuario($rol);
        $reposteria = Reposteria::factory()->for($usuario, 'propietario')->create();

        return [app(ReposteriaEstadoService::class)->aprobar($reposteria, $this->usuario('superadmin')), $usuario];
    }

    private function pedidoListo(): array
    {
        [$reposteria, $admin] = $this->contexto();
        $cliente = Cliente::factory()->for($reposteria)->create();
        $producto = Producto::factory()->for($reposteria)->create(['nombre' => 'Pedido histórico', 'precio' => '80.00']);
        $pedido = app(PedidoService::class)->crear($admin, $reposteria, ['cliente_id' => $cliente->id], [['producto_id' => $producto->id, 'cantidad' => 2]]);
        $pedido = app(PedidoService::class)->cambiarEstado($admin, $pedido, PedidoEstado::Confirmado);
        $pedido = app(PedidoService::class)->cambiarEstado($admin, $pedido, PedidoEstado::EnProduccion);
        $pedido = app(PedidoService::class)->cambiarEstado($admin, $pedido, PedidoEstado::Listo);

        return [$pedido->load('detalles'), $admin, $producto];
    }

    private function miembro(Reposteria $reposteria, string $rol): User
    {
        $usuario = $this->usuario($rol);
        $reposteria->usuarios()->attach($usuario);

        return $usuario;
    }

    private function usuario(string $rol, bool $activo = true): User
    {
        return User::factory()->for(Role::query()->where('nombre', $rol)->firstOrFail())->create(['activo' => $activo]);
    }
}
