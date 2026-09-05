<?php

namespace App\Services;

use App\Enums\PedidoEstado;
use App\Enums\VentaEstado;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Reposteria;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Support\Dinero;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VentaService
{
    public function __construct(private VentaAccessService $acceso) {}

    public function crearDirecta(User $actor, Reposteria $reposteria, array $datos, array $detalles): Venta
    {
        $this->acceso->autorizarOperacion($actor, $reposteria);

        if ($detalles === []) {
            throw new DomainException('La venta debe contener al menos un detalle.');
        }

        return DB::transaction(function () use ($reposteria, $datos, $detalles): Venta {
            $datos = $this->validarCabecera($datos, $reposteria);
            $venta = $this->crearCabecera($reposteria, $datos);

            foreach ($detalles as $detalle) {
                $this->crearDetalleDirecto($venta, $detalle);
            }

            $this->aplicarTotales($venta, $datos['descuento']);

            return $venta->refresh()->load('detalles');
        });
    }

    public function crearDesdePedido(User $actor, Pedido $pedido, array $datos = []): Venta
    {
        $this->acceso->autorizarOperacion($actor, $pedido->reposteria);

        return DB::transaction(function () use ($pedido, $datos): Venta {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if (! in_array($pedido->estado, [PedidoEstado::Listo, PedidoEstado::Entregado], true)) {
                throw new DomainException('Solo pedidos listos o entregados pueden convertirse en venta.');
            }

            if (Venta::query()->where('pedido_id', $pedido->id)->where('estado', '!=', VentaEstado::Anulada->value)->exists()) {
                throw new DomainException('El pedido ya tiene una venta activa.');
            }

            if (! $pedido->detalles()->exists()) {
                throw new DomainException('No se puede vender un pedido vacío.');
            }

            $datos['cliente_id'] = $pedido->cliente_id;
            $datos = $this->validarCabecera($datos, $pedido->reposteria);
            $venta = $this->crearCabecera($pedido->reposteria, $datos, $pedido->id);

            $pedido->detalles()->each(function (PedidoDetalle $detalle) use ($venta): void {
                $ventaDetalle = new VentaDetalle;
                $ventaDetalle->forceFill([
                    'venta_id' => $venta->id,
                    'producto_id' => $detalle->producto_id,
                    'producto_variante_id' => $detalle->producto_variante_id,
                    'nombre_producto' => $detalle->nombre_producto,
                    'nombre_variante' => $detalle->nombre_variante,
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => $detalle->precio_unitario,
                    'subtotal' => $detalle->subtotal,
                ])->save();
            });

            $this->aplicarTotales($venta, $datos['descuento']);

            return $venta->refresh()->load('detalles');
        });
    }

    public function anular(User $actor, Venta $venta): Venta
    {
        $this->acceso->autorizarAdministracion($actor, $venta->reposteria);

        return DB::transaction(function () use ($venta): Venta {
            $venta = Venta::query()->lockForUpdate()->findOrFail($venta->id);

            if ($venta->estado === VentaEstado::Anulada) {
                throw new DomainException('La venta ya está anulada.');
            }

            $venta->forceFill(['estado' => VentaEstado::Anulada])->save();

            return $venta->refresh();
        });
    }

    private function crearCabecera(Reposteria $reposteria, array $datos, ?int $pedidoId = null): Venta
    {
        $venta = new Venta;
        $venta->forceFill([
            'reposteria_id' => $reposteria->id,
            'pedido_id' => $pedidoId,
            'cliente_id' => $datos['cliente_id'],
            'estado' => VentaEstado::Pendiente,
            'fecha_venta' => now(),
            'subtotal' => 0,
            'descuento' => 0,
            'total' => 0,
            'monto_pagado' => 0,
            'saldo' => 0,
            'observaciones' => $datos['observaciones'],
        ])->save();

        return $venta;
    }

    private function crearDetalleDirecto(Venta $venta, array $datos): void
    {
        $datos = Validator::make($datos, [
            'producto_id' => ['required', 'integer'],
            'producto_variante_id' => ['nullable', 'integer'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
        ])->validate();

        $producto = Producto::query()->findOrFail($datos['producto_id']);

        if (! $producto->activo || $producto->reposteria_id !== $venta->reposteria_id) {
            throw ValidationException::withMessages(['producto_id' => 'Producto no válido para esta repostería.']);
        }

        $variante = null;
        if ($datos['producto_variante_id'] ?? null) {
            $variante = ProductoVariante::query()->findOrFail($datos['producto_variante_id']);
            if (! $variante->activo || $variante->producto_id !== $producto->id) {
                throw ValidationException::withMessages(['producto_variante_id' => 'Variante no válida para el producto.']);
            }
        }

        if ($producto->maneja_stock && (($variante?->stock ?? $producto->stock) < $datos['cantidad'])) {
            throw ValidationException::withMessages(['cantidad' => 'Stock insuficiente.']);
        }

        $precio = (string) ($variante?->precio ?? $producto->precio);
        $subtotal = Dinero::aCentavos($precio) * $datos['cantidad'];

        if ($subtotal > 999999999999) {
            throw ValidationException::withMessages(['cantidad' => 'El subtotal excede el máximo permitido.']);
        }

        $detalle = new VentaDetalle;
        $detalle->forceFill([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'producto_variante_id' => $variante?->id,
            'nombre_producto' => $producto->nombre,
            'nombre_variante' => $variante?->nombre,
            'cantidad' => $datos['cantidad'],
            'precio_unitario' => $precio,
            'subtotal' => Dinero::formatear($subtotal),
        ])->save();
    }

    private function aplicarTotales(Venta $venta, string $descuento): void
    {
        $subtotal = $venta->detalles()->get()->sum(fn (VentaDetalle $detalle) => Dinero::aCentavos($detalle->subtotal));
        $descuentoCentavos = Dinero::aCentavos($descuento);

        if ($descuentoCentavos > $subtotal) {
            throw ValidationException::withMessages(['descuento' => 'El descuento no puede superar el subtotal.']);
        }

        $total = $subtotal - $descuentoCentavos;
        $venta->forceFill([
            'subtotal' => Dinero::formatear($subtotal),
            'descuento' => Dinero::formatear($descuentoCentavos),
            'total' => Dinero::formatear($total),
            'monto_pagado' => '0.00',
            'saldo' => Dinero::formatear($total),
            'estado' => VentaEstado::Pendiente,
        ])->save();
    }

    private function validarCabecera(array $datos, Reposteria $reposteria): array
    {
        $datos = Validator::make($datos, [
            'cliente_id' => ['nullable', 'integer'],
            'descuento' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'observaciones' => ['nullable', 'string', 'max:4000'],
        ])->validate();

        $datos['cliente_id'] ??= null;
        $datos['descuento'] = isset($datos['descuento']) ? (string) $datos['descuento'] : '0.00';
        $datos['observaciones'] ??= null;

        if ($datos['cliente_id']) {
            $cliente = Cliente::query()->findOrFail($datos['cliente_id']);
            if (! $cliente->activo || $cliente->reposteria_id !== $reposteria->id) {
                throw ValidationException::withMessages(['cliente_id' => 'Cliente no válido para esta repostería.']);
            }
        }

        return $datos;
    }
}
