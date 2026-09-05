<?php

namespace App\Services;

use App\Enums\MetodoPago;
use App\Enums\VentaEstado;
use App\Models\Pago;
use App\Models\User;
use App\Models\Venta;
use App\Support\Dinero;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PagoService
{
    public function __construct(private VentaAccessService $acceso) {}

    public function registrar(User $actor, Venta $venta, array $datos): Pago
    {
        $this->acceso->autorizarOperacion($actor, $venta->reposteria);
        $datos = $this->validar($datos);

        return DB::transaction(function () use ($venta, $datos): Pago {
            $venta = Venta::query()->lockForUpdate()->findOrFail($venta->id);
            $this->recalcular($venta);

            if ($venta->estado === VentaEstado::Anulada) {
                throw new DomainException('No se registran pagos en una venta anulada.');
            }

            $monto = Dinero::aCentavos((string) $datos['monto']);
            $saldo = Dinero::aCentavos($venta->saldo);

            if ($saldo === 0) {
                throw new DomainException('La venta ya está completamente pagada.');
            }

            if ($monto > $saldo) {
                throw ValidationException::withMessages(['monto' => 'El pago no puede superar el saldo.']);
            }

            $pago = new Pago;
            $pago->forceFill([
                'venta_id' => $venta->id,
                'metodo' => MetodoPago::from($datos['metodo']),
                'monto' => Dinero::formatear($monto),
                'fecha_pago' => now(),
                'referencia' => $datos['referencia'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
            ])->save();

            $this->recalcular($venta);

            return $pago->refresh();
        });
    }

    public function anular(User $actor, Pago $pago): void
    {
        $this->acceso->autorizarAdministracion($actor, $pago->venta->reposteria);

        DB::transaction(function () use ($pago): void {
            $venta = Venta::query()->lockForUpdate()->findOrFail($pago->venta_id);
            $pago = Pago::query()->lockForUpdate()->findOrFail($pago->id);
            $pago->delete();
            $this->recalcular($venta);
        });
    }

    private function recalcular(Venta $venta): void
    {
        $pagado = $venta->pagos()->get()->sum(fn (Pago $pago) => Dinero::aCentavos($pago->monto));
        $total = Dinero::aCentavos($venta->total);
        $saldo = max(0, $total - $pagado);

        $cambios = [
            'monto_pagado' => Dinero::formatear($pagado),
            'saldo' => Dinero::formatear($saldo),
        ];

        if ($venta->estado !== VentaEstado::Anulada) {
            $cambios['estado'] = match (true) {
                $pagado === 0 => VentaEstado::Pendiente,
                $pagado < $total => VentaEstado::Parcial,
                default => VentaEstado::Pagada,
            };
        }

        $venta->forceFill($cambios)->save();
        $venta->refresh();
    }

    private function validar(array $datos): array
    {
        return Validator::make($datos, [
            'metodo' => ['required', Rule::enum(MetodoPago::class)],
            'monto' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ])->validate();
    }
}
