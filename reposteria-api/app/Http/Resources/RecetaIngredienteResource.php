<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecetaIngredienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'unidad_medida' => $this->unidad_medida->value,
            'cantidad' => $this->formatearCantidad((string) $this->pivot->cantidad),
        ];
    }

    private function formatearCantidad(string $cantidad): string
    {
        [$entero, $decimales] = array_pad(explode('.', $cantidad, 2), 2, '');

        return $entero.'.'.substr(str_pad($decimales, 3, '0'), 0, 3);
    }
}
