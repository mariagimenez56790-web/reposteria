<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'unidad_medida' => $this->unidad_medida->value,
            'stock_actual' => $this->stock_actual,
            'stock_minimo' => $this->stock_minimo,
            'costo_unitario' => $this->costo_unitario,
            'activo' => $this->activo,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
