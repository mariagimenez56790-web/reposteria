<?php

namespace App\Services;

use App\Enums\MovimientoInventarioTipo;
use App\Models\Ingrediente;
use App\Models\MovimientoInventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    public function __construct(private InventarioAccessService $acceso) {}

    public function entrada(User $actor, Ingrediente $ingrediente, array $datos): MovimientoInventario
    {
        return $this->registrar($actor, $ingrediente, MovimientoInventarioTipo::Entrada, $datos);
    }

    public function salida(User $actor, Ingrediente $ingrediente, array $datos): MovimientoInventario
    {
        return $this->registrar($actor, $ingrediente, MovimientoInventarioTipo::Salida, $datos);
    }

    public function ajustePositivo(User $actor, Ingrediente $ingrediente, array $datos): MovimientoInventario
    {
        return $this->registrar($actor, $ingrediente, MovimientoInventarioTipo::AjustePositivo, $datos);
    }

    public function ajusteNegativo(User $actor, Ingrediente $ingrediente, array $datos): MovimientoInventario
    {
        return $this->registrar($actor, $ingrediente, MovimientoInventarioTipo::AjusteNegativo, $datos);
    }

    private function registrar(User $actor, Ingrediente $ingrediente, MovimientoInventarioTipo $tipo, array $datos): MovimientoInventario
    {
        $tipo === MovimientoInventarioTipo::Salida ? $this->acceso->autorizarSalida($actor, $ingrediente->reposteria) : $this->acceso->autorizarAdministracion($actor, $ingrediente->reposteria);
        $datos = Validator::make($datos, ['cantidad' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'], 'motivo' => ['nullable', 'string', 'max:255'], 'referencia_tipo' => ['nullable', 'string', 'max:80'], 'referencia_id' => ['nullable', 'integer', 'min:1']])->validate();

        return DB::transaction(function () use ($actor, $ingrediente, $tipo, $datos) {
            $bloqueado = Ingrediente::query()->lockForUpdate()->findOrFail($ingrediente->id);
            if ($bloqueado->trashed() || ! $bloqueado->activo) {
                throw ValidationException::withMessages(['ingrediente_id' => 'El ingrediente no está disponible.']);
            }
            $anterior = $this->milesimas($bloqueado->stock_actual);
            $cantidad = $this->milesimas((string) $datos['cantidad']);
            $nuevo = $tipo->incrementa() ? $anterior + $cantidad : $anterior - $cantidad;
            if ($nuevo < 0) {
                throw ValidationException::withMessages(['cantidad' => 'Stock insuficiente.']);
            }
            $bloqueado->forceFill(['stock_actual' => $this->formatear($nuevo)])->save();
            $movimiento = new MovimientoInventario;
            $movimiento->forceFill(['reposteria_id' => $bloqueado->reposteria_id, 'ingrediente_id' => $bloqueado->id, 'tipo' => $tipo, 'cantidad' => $this->formatear($cantidad), 'stock_anterior' => $this->formatear($anterior), 'stock_nuevo' => $this->formatear($nuevo), 'motivo' => $datos['motivo'] ?? null, 'referencia_tipo' => $datos['referencia_tipo'] ?? null, 'referencia_id' => $datos['referencia_id'] ?? null, 'creado_por' => $actor->id, 'fecha_movimiento' => now()])->save();

            return $movimiento->refresh();
        });
    }

    private function milesimas(string $valor): int
    {
        [$entero, $decimal] = array_pad(explode('.', $valor, 2), 2, '');

        return ((int) $entero * 1000) + (int) str_pad(substr($decimal, 0, 3), 3, '0');
    }

    private function formatear(int $valor): string
    {
        return intdiv($valor, 1000).'.'.str_pad((string) ($valor % 1000), 3, '0', STR_PAD_LEFT);
    }
}
