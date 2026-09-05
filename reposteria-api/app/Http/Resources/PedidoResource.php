<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente' => $this->cliente === null ? null : new ClienteResource($this->cliente),
            'estado' => $this->estado->value,
            'fecha_pedido' => $this->fecha_pedido->toISOString(),
            'fecha_entrega' => $this->fecha_entrega?->toISOString(),
            'observaciones' => $this->observaciones,
            'total' => $this->total,
            'detalles' => PedidoDetalleResource::collection($this->whenLoaded('detalles')),
        ];
    }
}
