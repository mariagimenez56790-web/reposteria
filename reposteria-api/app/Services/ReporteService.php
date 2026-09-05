<?php

namespace App\Services;

use App\Enums\PedidoEstado;
use App\Enums\VentaEstado;
use App\Models\Ingrediente;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Reposteria;
use App\Models\Venta;
use App\Support\Dinero;

class ReporteService
{
    public function ventas(Reposteria $r, array $f): array
    {
        $q = Venta::query()->where('reposteria_id', $r->id);
        $this->fechas($q, 'fecha_venta', $f);
        $q->when(isset($f['estado']), fn ($x) => $x->where('estado', $f['estado']))->when(isset($f['cliente_id']), fn ($x) => $x->where('cliente_id', $f['cliente_id']));
        $economicas = (clone $q)->where('estado', '!=', VentaEstado::Anulada);
        $pagado = Pago::query()->whereIn('venta_id', (clone $economicas)->select('id'))->sum('monto');
        $resumen = ['cantidad_ventas' => (clone $q)->count(), 'subtotal_acumulado' => $this->dinero((clone $economicas)->sum('subtotal')), 'descuentos_acumulados' => $this->dinero((clone $economicas)->sum('descuento')), 'total_vendido' => $this->dinero((clone $economicas)->sum('total')), 'total_pagado' => $this->dinero($pagado), 'saldo_pendiente' => $this->dinero((clone $economicas)->sum('saldo'))];
        $p = $q->with('cliente:id,nombre')->latest('fecha_venta')->latest('id')->paginate($f['per_page'] ?? 15);
        $data = $p->getCollection()->map(fn ($v) => ['id' => $v->id, 'cliente' => $v->cliente?->only(['id', 'nombre']), 'estado' => $v->estado->value, 'fecha_venta' => $v->fecha_venta->toISOString(), 'subtotal' => $v->subtotal, 'descuento' => $v->descuento, 'total' => $v->total, 'monto_pagado' => $v->monto_pagado, 'saldo' => $v->saldo]);

        return $this->respuesta($resumen, $data, $p);
    }

    public function pedidos(Reposteria $r, array $f): array
    {
        $q = Pedido::query()->where('reposteria_id', $r->id);
        $this->fechas($q, 'fecha_pedido', $f);
        $q->when(isset($f['estado']), fn ($x) => $x->where('estado', $f['estado']))->when(isset($f['cliente_id']), fn ($x) => $x->where('cliente_id', $f['cliente_id']));
        $conteos = (clone $q)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $resumen = ['total_pedidos' => (clone $q)->count(), 'por_estado' => collect(PedidoEstado::cases())->mapWithKeys(fn ($e) => [$e->value => (int) ($conteos[$e->value] ?? 0)])->all(), 'valor_no_cancelado' => $this->dinero((clone $q)->where('estado', '!=', PedidoEstado::Cancelado)->sum('total'))];
        $p = $q->with('cliente:id,nombre')->latest('fecha_pedido')->latest('id')->paginate($f['per_page'] ?? 15);
        $data = $p->getCollection()->map(fn ($x) => ['id' => $x->id, 'cliente' => $x->cliente?->only(['id', 'nombre']), 'estado' => $x->estado->value, 'total' => $x->total, 'fecha_pedido' => $x->fecha_pedido->toISOString(), 'fecha_entrega' => $x->fecha_entrega?->toISOString()]);

        return $this->respuesta($resumen, $data, $p);
    }

    public function inventario(Reposteria $r, array $f): array
    {
        $base = Ingrediente::query()->where('reposteria_id', $r->id);
        $resumen = ['total_ingredientes' => (clone $base)->count(), 'ingredientes_activos' => (clone $base)->where('activo', true)->count(), 'stock_bajo' => (clone $base)->whereColumn('stock_actual', '<=', 'stock_minimo')->count(), 'sin_stock' => (clone $base)->where('stock_actual', '<=', 0)->count()];
        $q = clone $base;
        $q->when(isset($f['search']), fn ($x) => $x->where('nombre', 'like', '%'.$f['search'].'%'))->when(isset($f['activo']), fn ($x) => $x->where('activo', $f['activo']))->when(isset($f['unidad']), fn ($x) => $x->where('unidad_medida', $f['unidad']))->when(($f['stock_bajo'] ?? false), fn ($x) => $x->whereColumn('stock_actual', '<=', 'stock_minimo'))->when(($f['sin_stock'] ?? false), fn ($x) => $x->where('stock_actual', '<=', 0));
        $p = $q->orderBy('nombre')->orderBy('id')->paginate($f['per_page'] ?? 15);
        $data = $p->getCollection()->map(fn ($i) => ['id' => $i->id, 'nombre' => $i->nombre, 'unidad_medida' => $i->unidad_medida->value, 'stock_actual' => $i->stock_actual, 'stock_minimo' => $i->stock_minimo, 'costo_unitario' => $i->costo_unitario, 'activo' => $i->activo]);

        return $this->respuesta($resumen, $data, $p);
    }

    private function fechas($q, string $campo, array $f): void
    {
        $q->when(isset($f['fecha_desde']), fn ($x) => $x->whereDate($campo, '>=', $f['fecha_desde']))->when(isset($f['fecha_hasta']), fn ($x) => $x->whereDate($campo, '<=', $f['fecha_hasta']));
    }

    private function dinero($v): string
    {
        return Dinero::formatear(Dinero::aCentavos((string) ($v ?? 0)));
    }

    private function respuesta(array $resumen, $data, $p): array
    {
        return ['resumen' => $resumen, 'data' => $data, 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]];
    }
}
