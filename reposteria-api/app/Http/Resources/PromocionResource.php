<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromocionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ahora = now();

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tipo_descuento' => $this->tipo_descuento->value,
            'valor_descuento' => $this->valor_descuento,
            'fecha_inicio' => $this->fecha_inicio->toISOString(),
            'fecha_fin' => $this->fecha_fin->toISOString(),
            'activo' => $this->activo,
            'vigente' => $this->activo && ! $this->trashed() && $this->fecha_inicio->lte($ahora) && $this->fecha_fin->gte($ahora),
            'productos' => $this->whenLoaded('productos', fn () => $this->productos->map(fn ($producto) => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'precio' => $producto->precio,
                'activo' => $producto->activo,
            ])),
            'variantes' => $this->whenLoaded('variantes', fn () => $this->variantes->map(fn ($variante) => [
                'id' => $variante->id,
                'producto_id' => $variante->producto_id,
                'nombre' => $variante->nombre,
                'precio' => $variante->precio,
                'activo' => $variante->activo,
            ])),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
