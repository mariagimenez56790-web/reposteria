<?php

namespace App\Services;

use App\Enums\PedidoEstado;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PedidoService
{
    public function crear(User $actor, Reposteria $reposteria, array $datos, array $detalles): Pedido
    {
        $this->autorizar($actor, $reposteria, ['admin', 'vendedor']);
        if ($detalles === []) {
            throw new DomainException('El pedido debe contener al menos un detalle.');
        }

        return DB::transaction(function () use ($reposteria, $datos, $detalles): Pedido {
            $datos = $this->validarPedido($datos, $reposteria);
            $pedido = new Pedido;
            $pedido->forceFill($datos + ['reposteria_id' => $reposteria->id, 'estado' => PedidoEstado::Pendiente, 'fecha_pedido' => now(), 'total' => 0])->save();
            foreach ($detalles as $detalle) {
                $this->crearDetalle($pedido, $detalle);
            }
            $this->recalcular($pedido);

            return $pedido->refresh()->load('detalles');
        });
    }

    public function actualizar(User $actor, Pedido $pedido, array $datos): Pedido
    {
        $this->autorizarEdicion($actor, $pedido);
        $pedido->forceFill($this->validarPedido($datos, $pedido->reposteria))->save();

        return $pedido->refresh();
    }

    public function agregarDetalle(User $actor, Pedido $pedido, array $datos): PedidoDetalle
    {
        $this->autorizarEdicion($actor, $pedido);

        return DB::transaction(function () use ($pedido, $datos): PedidoDetalle {
            $detalle = $this->crearDetalle($pedido, $datos);
            $this->recalcular($pedido);

            return $detalle;
        });
    }

    public function modificarDetalle(User $actor, PedidoDetalle $detalle, array $datos): PedidoDetalle
    {
        $pedido = $detalle->pedido;
        $this->autorizarEdicion($actor, $pedido);

        return DB::transaction(function () use ($detalle, $pedido, $datos): PedidoDetalle {
            $detalle->forceFill($this->datosDetalle($pedido, $datos))->save();
            $this->recalcular($pedido);

            return $detalle->refresh();
        });
    }

    public function eliminarDetalle(User $actor, PedidoDetalle $detalle): void
    {
        $pedido = $detalle->pedido;
        $this->autorizarEdicion($actor, $pedido);
        DB::transaction(function () use ($detalle, $pedido): void {
            $detalle->delete();
            $this->recalcular($pedido);
        });
    }

    public function cambiarEstado(User $actor, Pedido $pedido, PedidoEstado $destino): Pedido
    {
        $this->autorizarConsulta($actor, $pedido->reposteria);
        $origen = $pedido->estado;
        $permitidas = [
            PedidoEstado::Pendiente->value => [PedidoEstado::Confirmado, PedidoEstado::Cancelado],
            PedidoEstado::Confirmado->value => [PedidoEstado::EnProduccion, PedidoEstado::Cancelado],
            PedidoEstado::EnProduccion->value => [PedidoEstado::Listo],
            PedidoEstado::Listo->value => [PedidoEstado::Entregado],
        ];
        if (! in_array($destino, $permitidas[$origen->value] ?? [], true)) {
            throw new DomainException('Transición de estado no permitida.');
        }
        $rol = $actor->role?->nombre;
        $super = $actor->esSuperadmin();
        $autorizado = $super || $rol === 'admin'
            || ($rol === 'vendedor' && in_array($destino, [PedidoEstado::Confirmado, PedidoEstado::Cancelado], true))
            || ($rol === 'produccion' && in_array($destino, [PedidoEstado::EnProduccion, PedidoEstado::Listo], true));
        if (! $autorizado) {
            throw new AuthorizationException('No puede realizar esta transición.');
        }
        if ($destino === PedidoEstado::Confirmado && ! $pedido->detalles()->exists()) {
            throw new DomainException('No se puede confirmar un pedido vacío.');
        }
        $pedido->forceFill(['estado' => $destino])->save();

        return $pedido->refresh();
    }

    public function eliminar(User $actor, Pedido $pedido): void
    {
        $this->autorizar($actor, $pedido->reposteria, ['admin']);
        if (! in_array($pedido->estado, [PedidoEstado::Pendiente, PedidoEstado::Cancelado], true)) {
            throw new DomainException('Este pedido debe conservarse como historial.');
        }
        $pedido->delete();
    }

    private function crearDetalle(Pedido $pedido, array $datos): PedidoDetalle
    {
        $detalle = new PedidoDetalle;
        $detalle->forceFill($this->datosDetalle($pedido, $datos) + ['pedido_id' => $pedido->id])->save();

        return $detalle;
    }

    private function datosDetalle(Pedido $pedido, array $datos): array
    {
        $datos = Validator::make($datos, ['producto_id' => ['required', 'integer'], 'producto_variante_id' => ['nullable', 'integer'], 'cantidad' => ['required', 'integer', 'min:1']])->validate();
        $producto = Producto::query()->findOrFail($datos['producto_id']);
        if (! $producto->activo || $producto->reposteria_id !== $pedido->reposteria_id) {
            throw ValidationException::withMessages(['producto_id' => 'Producto no válido para esta repostería.']);
        }
        $variante = null;
        if ($datos['producto_variante_id'] ?? null) {
            $variante = ProductoVariante::query()->findOrFail($datos['producto_variante_id']);
            if (! $variante->activo || $variante->producto_id !== $producto->id) {
                throw ValidationException::withMessages(['producto_variante_id' => 'Variante no válida para el producto.']);
            }
        }
        $cantidad = $datos['cantidad'];
        if ($producto->maneja_stock && (($variante?->stock ?? $producto->stock) < $cantidad)) {
            throw ValidationException::withMessages(['cantidad' => 'Stock insuficiente.']);
        }
        $precio = $variante?->precio ?? $producto->precio;
        $centavos = $this->aCentavos((string) $precio);

        return ['producto_id' => $producto->id, 'producto_variante_id' => $variante?->id, 'nombre_producto' => $producto->nombre, 'nombre_variante' => $variante?->nombre, 'cantidad' => $cantidad, 'precio_unitario' => number_format($centavos / 100, 2, '.', ''), 'subtotal' => number_format(($centavos * $cantidad) / 100, 2, '.', '')];
    }

    private function recalcular(Pedido $pedido): void
    {
        $centavos = $pedido->detalles()->get()->sum(fn ($detalle) => $this->aCentavos($detalle->subtotal));
        $pedido->forceFill(['total' => number_format($centavos / 100, 2, '.', '')])->save();
    }

    private function validarPedido(array $datos, Reposteria $reposteria): array
    {
        $datos = Validator::make($datos, ['cliente_id' => ['nullable', 'integer'], 'fecha_entrega' => ['nullable', 'date', 'after_or_equal:today'], 'observaciones' => ['nullable', 'string', 'max:4000']])->validate();
        if ($datos['cliente_id'] ?? null) {
            $cliente = Cliente::query()->findOrFail($datos['cliente_id']);
            if ($cliente->reposteria_id !== $reposteria->id || ! $cliente->activo) {
                throw ValidationException::withMessages(['cliente_id' => 'Cliente no válido para esta repostería.']);
            }
        }

        return $datos;
    }

    private function autorizarEdicion(User $actor, Pedido $pedido): void
    {
        $this->autorizar($actor, $pedido->reposteria, ['admin', 'vendedor']);
        if ($pedido->estado !== PedidoEstado::Pendiente) {
            throw new DomainException('Solo se editan pedidos pendientes.');
        }
    }

    private function autorizarConsulta(User $actor, Reposteria $reposteria): void
    {
        $this->autorizar($actor, $reposteria, ['admin', 'vendedor', 'produccion']);
    }

    private function autorizar(User $actor, Reposteria $reposteria, array $roles): void
    {
        if ($actor->esSuperadmin()) {
            return;
        }
        if ($actor->activo && in_array($actor->role?->nombre, $roles, true) && $actor->puedeOperarEnReposteria($reposteria)) {
            return;
        }
        throw new AuthorizationException('No tiene autorización para operar este pedido.');
    }

    private function aCentavos(string $importe): int
    {
        [$entero, $decimales] = array_pad(explode('.', $importe, 2), 2, '');

        return ((int) $entero * 100) + (int) substr(str_pad($decimales, 2, '0'), 0, 2);
    }
}
