<?php

namespace App\Services;

use App\Enums\PedidoEstado;
use App\Enums\VentaEstado;
use App\Models\Cliente;
use App\Models\Ingrediente;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reposteria;
use App\Models\Venta;
use App\Support\Dinero;

class DashboardService
{
    public function obtener(Reposteria $reposteria): array
    {
        $hoy = now()->toDateString();
        $ventasActivas = Venta::query()->where('reposteria_id', $reposteria->id)->where('estado', '!=', VentaEstado::Anulada);
        $ventasHoy = (clone $ventasActivas)->whereDate('fecha_venta', $hoy);
        $pedidos = Pedido::query()->where('reposteria_id', $reposteria->id);
        $conteos = (clone $pedidos)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $stockBajo = Ingrediente::query()->where('reposteria_id', $reposteria->id)->where('activo', true)->whereColumn('stock_actual', '<=', 'stock_minimo');

        return [
            'metricas' => [
                'ventas_hoy' => (clone $ventasHoy)->count(),
                'total_vendido_hoy' => $this->dinero((clone $ventasHoy)->sum('total')),
                'ingresos_cobrados_hoy' => $this->dinero(Pago::query()->whereHas('venta', fn ($q) => $q->where('reposteria_id', $reposteria->id)->where('estado', '!=', VentaEstado::Anulada))->whereDate('fecha_pago', $hoy)->sum('monto')),
                'saldo_pendiente' => $this->dinero((clone $ventasActivas)->sum('saldo')),
                'clientes_activos' => Cliente::query()->where('reposteria_id', $reposteria->id)->where('activo', true)->count(),
                'productos_activos' => Producto::query()->where('reposteria_id', $reposteria->id)->where('activo', true)->count(),
                'ingredientes_stock_bajo' => (clone $stockBajo)->count(),
            ],
            'pedidos' => collect(PedidoEstado::cases())->mapWithKeys(fn ($estado) => [$estado->value => (int) ($conteos[$estado->value] ?? 0)])->all(),
            'ventas_recientes' => Venta::query()->where('reposteria_id', $reposteria->id)->where('estado', '!=', VentaEstado::Anulada)->with('cliente:id,nombre')->latest('fecha_venta')->latest('id')->limit(5)->get()->map(fn ($v) => [
                'id' => $v->id, 'cliente' => $v->cliente?->only(['id', 'nombre']), 'total' => $v->total,
                'monto_pagado' => $v->monto_pagado, 'saldo' => $v->saldo, 'estado' => $v->estado->value,
                'fecha_venta' => $v->fecha_venta->toISOString(),
            ]),
            'pedidos_recientes' => (clone $pedidos)->with('cliente:id,nombre')->latest('fecha_pedido')->latest('id')->limit(5)->get()->map(fn ($p) => [
                'id' => $p->id, 'cliente' => $p->cliente?->only(['id', 'nombre']), 'estado' => $p->estado->value,
                'total' => $p->total, 'fecha_pedido' => $p->fecha_pedido->toISOString(), 'fecha_entrega' => $p->fecha_entrega?->toISOString(),
            ]),
            'stock_bajo' => (clone $stockBajo)->orderBy('stock_actual')->orderBy('id')->limit(10)->get()->map(fn ($i) => [
                'id' => $i->id, 'nombre' => $i->nombre, 'unidad_medida' => $i->unidad_medida->value,
                'stock_actual' => $i->stock_actual, 'stock_minimo' => $i->stock_minimo,
            ]),
        ];
    }

    private function dinero(string|int|float|null $valor): string
    {
        return Dinero::formatear(Dinero::aCentavos((string) ($valor ?? 0)));
    }
}
