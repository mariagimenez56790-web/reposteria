<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente === null ? null : new ClienteResource($this->cliente)),
            'pedido' => $this->whenLoaded('pedido', fn () => $this->pedido === null ? null : [
                'id' => $this->pedido->id,
                'estado' => $this->pedido->estado->value,
            ]),
            'estado' => $this->estado->value,
            'fecha_venta' => $this->fecha_venta->toISOString(),
            'subtotal' => $this->subtotal,
            'descuento' => $this->descuento,
            'total' => $this->total,
            'monto_pagado' => $this->monto_pagado,
            'saldo' => $this->saldo,
            'observaciones' => $this->observaciones,
            'detalles' => VentaDetalleResource::collection($this->whenLoaded('detalles')),
            'pagos' => PagoResource::collection($this->whenLoaded('pagos')),
        ];
    }
}
