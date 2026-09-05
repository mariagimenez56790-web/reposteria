<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\PedidoEstado;
use App\Enums\VentaEstado;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoService;
use App\Services\PedidoService;
use App\Services\ReposteriaEstadoService;
use App\Services\VentaService;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VentaPagoManagementTest extends TestCase
{
    use RefreshDatabase;

    private VentaService $ventas;

    private PagoService $pagos;

    private PedidoService $pedidos;

    private ReposteriaEstadoService $estados;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->ventas = app(VentaService::class);
        $this->pagos = app(PagoService::class);
        $this->pedidos = app(PedidoService::class);
        $this->estados = app(ReposteriaEstadoService::class);
    }

    public function test_financial_tables_have_the_expected_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('ventas', [
            'id', 'reposteria_id', 'pedido_id', 'cliente_id', 'estado', 'fecha_venta',
            'subtotal', 'descuento', 'total', 'monto_pagado', 'saldo', 'observaciones', 'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('venta_detalles', [
            'id', 'venta_id', 'producto_id', 'producto_variante_id', 'nombre_producto',
            'nombre_variante', 'cantidad', 'precio_unitario', 'subtotal',
        ]));
        $this->assertTrue(Schema::hasColumns('pagos', [
            'id', 'venta_id', 'metodo', 'monto', 'fecha_pago', 'referencia', 'observaciones', 'deleted_at',
        ]));
    }

    public function test_direct_sale_supports_optional_client_and_freezes_catalog_data(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $cliente = Cliente::factory()->for($reposteria)->create();
        $producto = Producto::factory()->for($reposteria)->create(['nombre' => 'Torta', 'precio' => '80.50']);
        $venta = $this->ventas->crearDirecta($admin, $reposteria, [
            'cliente_id' => $cliente->id,
            'descuento' => '1.00',
            'total' => '0.01',
            'estado' => VentaEstado::Pagada->value,
        ], [[
            'producto_id' => $producto->id,
            'cantidad' => 2,
            'precio_unitario' => '0.01',
            'subtotal' => '0.02',
        ]]);

        $this->assertTrue($venta->reposteria->is($reposteria));
        $this->assertTrue($venta->cliente->is($cliente));
        $this->assertNull($venta->pedido_id);
        $this->assertSame(VentaEstado::Pendiente, $venta->estado);
        $this->assertSame('161.00', $venta->subtotal);
        $this->assertSame('160.00', $venta->total);
        $this->assertSame('0.00', $venta->monto_pagado);
        $this->assertSame('160.00', $venta->saldo);
        $this->assertSame('80.50', $venta->detalles[0]->precio_unitario);

        $producto->update(['nombre' => 'Nombre nuevo', 'precio' => '100.00']);
        $detalle = $venta->detalles[0]->fresh();
        $this->assertSame('Torta', $detalle->nombre_producto);
        $this->assertSame('80.50', $detalle->precio_unitario);
    }

    public function test_direct_sale_can_have_no_client_and_valid_variant(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '50.00']);
        $variante = ProductoVariante::factory()->for($producto)->create([
            'nombre' => 'Grande',
            'precio' => '120.00',
        ]);
        $venta = $this->ventas->crearDirecta($admin, $reposteria, [], [[
            'producto_id' => $producto->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 2,
        ]]);

        $this->assertNull($venta->cliente_id);
        $this->assertSame('Grande', $venta->detalles[0]->nombre_variante);
        $this->assertSame('120.00', $venta->detalles[0]->precio_unitario);
        $this->assertSame('240.00', $venta->total);
        $this->assertTrue($venta->detalles[0]->variante->is($variante));
    }

    public function test_cross_tenant_data_and_wrong_variant_roll_back_the_sale(): void
    {
        [$a, $adminA] = $this->contexto();
        [$b] = $this->contexto();
        $clienteB = Cliente::factory()->for($b)->create();
        $productoA = Producto::factory()->for($a)->create();
        $productoB = Producto::factory()->for($b)->create();
        $varianteA = ProductoVariante::factory()->for($productoA)->create();

        $casos = [
            [['cliente_id' => $clienteB->id], [['producto_id' => $productoA->id, 'cantidad' => 1]]],
            [[], [['producto_id' => $productoB->id, 'cantidad' => 1]]],
            [[], [['producto_id' => $productoB->id, 'producto_variante_id' => $varianteA->id, 'cantidad' => 1]]],
        ];

        foreach ($casos as [$cabecera, $detalles]) {
            try {
                $this->ventas->crearDirecta($adminA, $a, $cabecera, $detalles);
                $this->fail('La venta cruzada no debió crearse.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('ventas', 0);
                $this->assertDatabaseCount('venta_detalles', 0);
            }
        }
    }

    public function test_discount_is_validated_and_total_is_calculated(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '100.00']);
        $venta = $this->ventas->crearDirecta($admin, $reposteria, ['descuento' => '20.00'], [[
            'producto_id' => $producto->id,
            'cantidad' => 2,
        ]]);
        $this->assertSame('200.00', $venta->subtotal);
        $this->assertSame('20.00', $venta->descuento);
        $this->assertSame('180.00', $venta->total);

        foreach (['-1.00', '201.00'] as $descuento) {
            try {
                $this->ventas->crearDirecta($admin, $reposteria, ['descuento' => $descuento], [[
                    'producto_id' => $producto->id,
                    'cantidad' => 2,
                ]]);
                $this->fail('El descuento inválido no debió aceptarse.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_sale_from_ready_order_copies_historical_details_and_client(): void
    {
        [$pedido, $admin, $producto, $variante] = $this->pedidoListo();
        $precioPedido = $pedido->detalles[0]->precio_unitario;
        $producto->update(['nombre' => 'Producto actualizado', 'precio' => '999.00']);
        $variante->update(['nombre' => 'Variante actualizada', 'precio' => '888.00']);

        $venta = $this->ventas->crearDesdePedido($admin, $pedido, ['descuento' => '5.00']);

        $this->assertSame($pedido->id, $venta->pedido_id);
        $this->assertSame($pedido->cliente_id, $venta->cliente_id);
        $this->assertSame($pedido->detalles[0]->nombre_producto, $venta->detalles[0]->nombre_producto);
        $this->assertSame($pedido->detalles[0]->nombre_variante, $venta->detalles[0]->nombre_variante);
        $this->assertSame($precioPedido, $venta->detalles[0]->precio_unitario);
        $this->assertSame('235.00', $venta->total);
        $this->assertTrue($pedido->ventas->first()->is($venta));
    }

    public function test_cancelled_order_is_rejected_and_active_order_cannot_be_sold_twice(): void
    {
        [$pedido, $admin] = $this->pedidoListo();
        $this->ventas->crearDesdePedido($admin, $pedido);

        try {
            $this->ventas->crearDesdePedido($admin, $pedido);
            $this->fail('El pedido no debió venderse dos veces.');
        } catch (DomainException) {
            $this->assertDatabaseCount('ventas', 1);
        }

        [$reposteria, $otroAdmin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create();
        $cancelado = $this->pedidos->crear($otroAdmin, $reposteria, [], [[
            'producto_id' => $producto->id,
            'cantidad' => 1,
        ]]);
        $cancelado = $this->pedidos->cambiarEstado($otroAdmin, $cancelado, PedidoEstado::Cancelado);

        $this->expectException(DomainException::class);
        $this->ventas->crearDesdePedido($otroAdmin, $cancelado);
    }

    public function test_partial_and_full_payments_recalculate_financial_fields(): void
    {
        [$venta, $admin] = $this->ventaDirecta('100.00');
        $primerPago = $this->pagos->registrar($admin, $venta, [
            'metodo' => MetodoPago::Efectivo->value,
            'monto' => '40.00',
        ]);
        $venta->refresh();
        $this->assertTrue($primerPago->venta->is($venta));
        $this->assertSame(MetodoPago::Efectivo, $primerPago->metodo);
        $this->assertSame(VentaEstado::Parcial, $venta->estado);
        $this->assertSame('40.00', $venta->monto_pagado);
        $this->assertSame('60.00', $venta->saldo);

        $this->pagos->registrar($admin, $venta, [
            'metodo' => MetodoPago::Qr->value,
            'monto' => '60.00',
            'referencia' => 'QR-001',
        ]);
        $venta->refresh();
        $this->assertSame(VentaEstado::Pagada, $venta->estado);
        $this->assertSame('100.00', $venta->monto_pagado);
        $this->assertSame('0.00', $venta->saldo);
        $this->assertCount(2, $venta->pagos);
    }

    public function test_invalid_payment_method_amount_overpayment_and_extra_payment_are_rejected(): void
    {
        [$venta, $admin] = $this->ventaDirecta('50.00');

        foreach ([
            ['metodo' => 'bitcoin', 'monto' => '10.00'],
            ['metodo' => 'efectivo', 'monto' => '0.00'],
            ['metodo' => 'efectivo', 'monto' => '-1.00'],
            ['metodo' => 'efectivo', 'monto' => '51.00'],
        ] as $datos) {
            try {
                $this->pagos->registrar($admin, $venta, $datos);
                $this->fail('El pago inválido no debió aceptarse.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('pagos', 0);
            }
        }

        $this->pagos->registrar($admin, $venta, ['metodo' => 'tarjeta', 'monto' => '50.00']);
        $this->expectException(DomainException::class);
        $this->pagos->registrar($admin, $venta, ['metodo' => 'otro', 'monto' => '1.00']);
    }

    public function test_payment_annulment_soft_deletes_and_recalculates_sale(): void
    {
        [$venta, $admin] = $this->ventaDirecta('100.00');
        $pago = $this->pagos->registrar($admin, $venta, ['metodo' => 'transferencia', 'monto' => '70.00']);
        $this->pagos->anular($admin, $pago);
        $venta->refresh();

        $this->assertSoftDeleted('pagos', ['id' => $pago->id]);
        $this->assertNotNull(Pago::withTrashed()->find($pago->id));
        $this->assertSame('0.00', $venta->monto_pagado);
        $this->assertSame('100.00', $venta->saldo);
        $this->assertSame(VentaEstado::Pendiente, $venta->estado);
    }

    public function test_admin_can_annul_sale_vendor_cannot_and_annulled_sale_rejects_payments(): void
    {
        [$venta, $admin, $reposteria] = $this->ventaDirecta('100.00');
        $vendedor = $this->miembro($reposteria, 'vendedor');

        try {
            $this->ventas->anular($vendedor, $venta);
            $this->fail('El vendedor no debió anular la venta.');
        } catch (AuthorizationException) {
            $this->assertSame(VentaEstado::Pendiente, $venta->fresh()->estado);
        }

        $venta = $this->ventas->anular($admin, $venta);
        $this->assertSame(VentaEstado::Anulada, $venta->estado);
        $this->assertNotNull($venta->fresh());
        $this->expectException(DomainException::class);
        $this->pagos->registrar($admin, $venta, ['metodo' => 'efectivo', 'monto' => '10.00']);
    }

    public function test_vendor_can_create_and_pay_but_production_client_inactive_and_other_tenant_are_blocked(): void
    {
        [$reposteria, $admin] = $this->contexto();
        $vendedor = $this->miembro($reposteria, 'vendedor');
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '30.00']);
        $venta = $this->ventas->crearDirecta($vendedor, $reposteria, [], [[
            'producto_id' => $producto->id,
            'cantidad' => 1,
        ]]);
        $this->pagos->registrar($vendedor, $venta, ['metodo' => 'efectivo', 'monto' => '10.00']);
        $this->assertSame(VentaEstado::Parcial, $venta->fresh()->estado);

        [$otraReposteria, $otroAdmin] = $this->contexto();
        $actores = [
            $this->miembro($reposteria, 'produccion'),
            $this->usuario('cliente'),
            $this->usuario('admin', false),
            $otroAdmin,
        ];

        foreach ($actores as $actor) {
            try {
                $this->pagos->registrar($actor, $venta, ['metodo' => 'efectivo', 'monto' => '1.00']);
                $this->fail('El usuario no debió operar la venta.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertFalse($otraReposteria->is($reposteria));
        $this->assertSame('10.00', $venta->fresh()->monto_pagado);
        $this->assertTrue($admin->perteneceAReposteria($reposteria));
    }

    public function test_superadmin_can_create_pay_and_annul_without_membership(): void
    {
        [$reposteria] = $this->contexto();
        $superadmin = $this->usuario('superadmin');
        $producto = Producto::factory()->for($reposteria)->create(['precio' => '25.00']);
        $venta = $this->ventas->crearDirecta($superadmin, $reposteria, [], [[
            'producto_id' => $producto->id,
            'cantidad' => 1,
        ]]);
        $this->pagos->registrar($superadmin, $venta, ['metodo' => 'qr', 'monto' => '25.00']);
        $this->assertSame(VentaEstado::Pagada, $venta->fresh()->estado);
        $this->assertSame(VentaEstado::Anulada, $this->ventas->anular($superadmin, $venta)->estado);
    }

    private function contexto(): array
    {
        $admin = $this->usuario('admin');
        $reposteria = Reposteria::factory()->for($admin, 'propietario')->create();

        return [$this->estados->aprobar($reposteria, $this->usuario('superadmin')), $admin];
    }

    private function ventaDirecta(string $precio): array
    {
        [$reposteria, $admin] = $this->contexto();
        $producto = Producto::factory()->for($reposteria)->create(['precio' => $precio]);
        $venta = $this->ventas->crearDirecta($admin, $reposteria, [], [[
            'producto_id' => $producto->id,
            'cantidad' => 1,
        ]]);

        return [$venta, $admin, $reposteria];
    }

    private function pedidoListo(): array
    {
        [$reposteria, $admin] = $this->contexto();
        $cliente = Cliente::factory()->for($reposteria)->create();
        $producto = Producto::factory()->for($reposteria)->create(['nombre' => 'Torta', 'precio' => '80.00']);
        $variante = ProductoVariante::factory()->for($producto)->create([
            'nombre' => 'Grande',
            'precio' => '120.00',
        ]);
        $pedido = $this->pedidos->crear($admin, $reposteria, ['cliente_id' => $cliente->id], [[
            'producto_id' => $producto->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 2,
        ]]);
        $pedido = $this->pedidos->cambiarEstado($admin, $pedido, PedidoEstado::Confirmado);
        $pedido = $this->pedidos->cambiarEstado($admin, $pedido, PedidoEstado::EnProduccion);
        $pedido = $this->pedidos->cambiarEstado($admin, $pedido, PedidoEstado::Listo);

        return [$pedido->load('detalles'), $admin, $producto, $variante];
    }

    private function miembro(Reposteria $reposteria, string $rol): User
    {
        $usuario = $this->usuario($rol);
        $reposteria->usuarios()->attach($usuario);

        return $usuario;
    }

    private function usuario(string $rol, bool $activo = true): User
    {
        return User::factory()
            ->for(Role::query()->where('nombre', $rol)->firstOrFail())
            ->create(['activo' => $activo]);
    }
}
