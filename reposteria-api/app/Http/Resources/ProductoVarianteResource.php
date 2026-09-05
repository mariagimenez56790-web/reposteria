<?php

namespace App\Http\Resources;

use App\Services\PromocionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoVarianteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $precio = app(PromocionService::class)->calcularPrecioPromocional($request->user(), $this->producto, $this->resource);
        $promocion = $this->promociones->firstWhere('id', $precio['promocion_id']) ?? $this->producto->promociones->firstWhere('id', $precio['promocion_id']);

        return [
            'id' => $this->id, 'nombre' => $this->nombre, 'precio' => $precio['precio_base'], 'precio_final' => $precio['precio_final'],
            'stock' => $this->stock,
            'promocion' => $promocion === null ? null : ['id' => $promocion->id, 'nombre' => $promocion->nombre, 'tipo' => $promocion->tipo_descuento->value, 'valor' => $promocion->valor_descuento, 'descuento' => $precio['descuento'], 'fecha_inicio' => $promocion->fecha_inicio->toISOString(), 'fecha_fin' => $promocion->fecha_fin->toISOString()],
        ];
    }
}
