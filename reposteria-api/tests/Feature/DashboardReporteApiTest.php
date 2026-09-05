<?php

namespace Tests\Feature;

use App\Enums\PedidoEstado;
use App\Enums\VentaEstado;
use App\Models\Cliente;
use App\Models\Ingrediente;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Models\Venta;
use App\Services\ReposteriaEstadoService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardReporteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_authentication_active_user_and_role_matrix(): void
    {
        [$r, $admin] = $this->tenant();
        $this->getJson("/api/v1/reposterias/{$r->id}/dashboard")->assertUnauthorized();
        Sanctum::actingAs($this->usuario('admin', false));
        $this->getJson("/api/v1/reposterias/{$r->id}/dashboard")->assertForbidden();
        foreach (['admin', 'vendedor'] as $rol) {
            $u = $rol === 'admin' ? $admin : $this->miembro($r, $rol);
            Sanctum::actingAs($u);
            $this->getJson("/api/v1/reposterias/{$r->id}/dashboard")->assertOk();
            $this->getJson("/api/v1/reposterias/{$r->id}/reportes/ventas")->assertOk();
        }
        $produccion = $this->miembro($r, 'produccion');
        Sanctum::actingAs($produccion);
        $this->getJson("/api/v1/reposterias/{$r->id}/dashboard")->assertForbidden();
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/ventas")->assertForbidden();
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/pedidos")->assertOk();
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/inventario")->assertOk();
        Sanctum::actingAs($this->usuario('cliente'));
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/pedidos")->assertForbidden();
        Sanctum::actingAs($this->usuario('superadmin'));
        $this->getJson("/api/v1/reposterias/{$r->id}/dashboard")->assertOk();
    }

    public function test_dashboard_metrics_money_deleted_payments_and_tenant_isolation(): void
    {
        [$a, $admin] = $this->tenant();
        [$b] = $this->tenant();
        $venta = Venta::factory()->for($a)->create(['subtotal' => '120.00', 'descuento' => '20.00', 'total' => '100.00', 'monto_pagado' => '40.00', 'saldo' => '60.00']);
        Pago::factory()->for($venta)->create(['monto' => '40.00']);
        $eliminado = Pago::factory()->for($venta)->create(['monto' => '25.00']);
        $eliminado->delete();
        Venta::factory()->for($a)->create(['estado' => VentaEstado::Anulada, 'total' => '999.00', 'saldo' => '999.00']);
        Venta::factory()->for($b)->create(['total' => '500.00', 'saldo' => '500.00']);
        foreach ([PedidoEstado::Pendiente, PedidoEstado::Confirmado, PedidoEstado::EnProduccion, PedidoEstado::Listo] as $estado) {
            Pedido::factory()->for($a)->create(['estado' => $estado]);
        }
        Pedido::factory()->for($b)->create();
        Cliente::factory()->for($a)->create();
        Cliente::factory()->for($b)->count(2)->create();
        Producto::factory()->for($a)->create();
        Producto::factory()->for($b)->create();
        Ingrediente::factory()->for($a)->create(['nombre' => 'Harina', 'stock_actual' => '2.500', 'stock_minimo' => '3.000']);
        Ingrediente::factory()->for($b)->create(['nombre' => 'Ajeno', 'stock_actual' => 0, 'stock_minimo' => 5]);
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/reposterias/{$a->id}/dashboard")->assertOk()
            ->assertJsonPath('metricas.ventas_hoy', 1)->assertJsonPath('metricas.total_vendido_hoy', '100.00')->assertJsonPath('metricas.ingresos_cobrados_hoy', '40.00')->assertJsonPath('metricas.saldo_pendiente', '60.00')->assertJsonPath('metricas.clientes_activos', 1)->assertJsonPath('metricas.productos_activos', 1)->assertJsonPath('metricas.ingredientes_stock_bajo', 1)->assertJsonPath('pedidos.pendiente', 1)->assertJsonPath('pedidos.confirmado', 1)->assertJsonPath('pedidos.en_produccion', 1)->assertJsonPath('pedidos.listo', 1)->assertJsonPath('stock_bajo.0.stock_actual', '2.500')->assertJsonMissing(['Ajeno']);
    }

    public function test_sales_report_filters_summarizes_paginates_and_validates_cross_tenant_client(): void
    {
        [$r, $admin] = $this->tenant();
        [$otra] = $this->tenant();
        $cliente = Cliente::factory()->for($r)->create();
        $ajeno = Cliente::factory()->for($otra)->create();
        $ventas = Venta::factory()->for($r)->count(2)->create(['cliente_id' => $cliente->id, 'subtotal' => '60.00', 'descuento' => '10.00', 'total' => '50.00', 'monto_pagado' => '20.00', 'saldo' => '30.00']);
        foreach ($ventas as $venta) {
            Pago::factory()->for($venta)->create(['monto' => '20.00']);
        }
        Venta::factory()->for($r)->create(['estado' => VentaEstado::Anulada, 'total' => '900.00']);
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/ventas?cliente_id={$cliente->id}&per_page=1")->assertOk()->assertJsonPath('resumen.cantidad_ventas', 2)->assertJsonPath('resumen.total_vendido', '100.00')->assertJsonPath('resumen.total_pagado', '40.00')->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/ventas?estado=anulada")->assertOk()->assertJsonPath('resumen.cantidad_ventas', 1)->assertJsonPath('resumen.total_vendido', '0.00');
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/ventas?cliente_id={$ajeno->id}")->assertUnprocessable();
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/ventas?fecha_desde=2024-01-01&fecha_hasta=2025-02-01")->assertUnprocessable();
    }

    public function test_order_and_inventory_reports_filter_and_preserve_formats(): void
    {
        [$r, $admin] = $this->tenant();
        $cliente = Cliente::factory()->for($r)->create();
        Pedido::factory()->for($r)->create(['cliente_id' => $cliente->id, 'estado' => PedidoEstado::Pendiente, 'total' => '25.00']);
        Pedido::factory()->for($r)->create(['estado' => PedidoEstado::Cancelado, 'total' => '80.00']);
        Ingrediente::factory()->for($r)->create(['nombre' => 'Azúcar', 'stock_actual' => '0.000', 'stock_minimo' => '2.000', 'costo_unitario' => '4.50']);
        Ingrediente::factory()->for($r)->create(['nombre' => 'Leche', 'stock_actual' => '8.000', 'stock_minimo' => '2.000']);
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/pedidos")->assertOk()->assertJsonPath('resumen.total_pedidos', 2)->assertJsonPath('resumen.por_estado.cancelado', 1)->assertJsonPath('resumen.valor_no_cancelado', '25.00');
        $this->getJson("/api/v1/reposterias/{$r->id}/reportes/inventario?search=Az&stock_bajo=1&sin_stock=1")->assertOk()->assertJsonPath('resumen.total_ingredientes', 2)->assertJsonPath('data.0.nombre', 'Azúcar')->assertJsonPath('data.0.stock_actual', '0.000')->assertJsonPath('data.0.costo_unitario', '4.50')->assertJsonCount(1, 'data');
    }

    public function test_report_endpoints_are_read_only_and_local_admin_is_tenant_scoped(): void
    {
        [$a, $admin] = $this->tenant();
        [$b] = $this->tenant();
        Sanctum::actingAs($admin);
        foreach (['dashboard', 'reportes/ventas', 'reportes/pedidos', 'reportes/inventario'] as $ruta) {
            $this->getJson("/api/v1/reposterias/{$b->id}/{$ruta}")->assertForbidden();
            $this->postJson("/api/v1/reposterias/{$a->id}/{$ruta}")->assertMethodNotAllowed();
            $this->patchJson("/api/v1/reposterias/{$a->id}/{$ruta}")->assertMethodNotAllowed();
            $this->deleteJson("/api/v1/reposterias/{$a->id}/{$ruta}")->assertMethodNotAllowed();
        }
    }

    private function tenant(): array
    {
        $admin = $this->usuario('admin');
        $r = Reposteria::factory()->for($admin, 'propietario')->create();

        return [app(ReposteriaEstadoService::class)->aprobar($r, $this->usuario('superadmin')), $admin];
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
