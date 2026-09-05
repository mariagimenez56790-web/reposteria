<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovimientoInventarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ingrediente' => $this->whenLoaded('ingrediente', fn () => [
                'id' => $this->ingrediente->id,
                'nombre' => $this->ingrediente->nombre,
                'unidad_medida' => $this->ingrediente->unidad_medida->value,
            ]),
            'tipo' => $this->tipo->value,
            'cantidad' => $this->cantidad,
            'stock_anterior' => $this->stock_anterior,
            'stock_nuevo' => $this->stock_nuevo,
            'motivo' => $this->motivo,
            'referencia_tipo' => $this->referencia_tipo,
            'referencia_id' => $this->referencia_id,
            'creado_por' => $this->whenLoaded('creadoPor', fn () => $this->creadoPor === null ? null : [
                'id' => $this->creadoPor->id,
                'nombre' => $this->creadoPor->name,
            ]),
            'fecha_movimiento' => $this->fecha_movimiento->toISOString(),
        ];
    }
}
