<?php

namespace App\Http\Resources;

use App\Services\PromocionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $precio = app(PromocionService::class)->calcularPrecioPromocional($request->user(), $this->resource);
        $promocion = $precio['promocion_id'] === null ? null : $this->promociones->firstWhere('id', $precio['promocion_id']);

        return [
            'id' => $this->id, 'categoria_id' => $this->categoria_id, 'nombre' => $this->nombre,
            'descripcion' => $this->descripcion, 'precio' => $precio['precio_base'], 'precio_final' => $precio['precio_final'],
            'imagen' => $this->imagen, 'personalizable' => $this->personalizable, 'maneja_stock' => $this->maneja_stock,
            'stock' => $this->stock, 'tiene_variantes' => $this->variantes_count > 0,
            'promocion' => $promocion === null ? null : ['id' => $promocion->id, 'nombre' => $promocion->nombre, 'tipo' => $promocion->tipo_descuento->value, 'valor' => $promocion->valor_descuento, 'descuento' => $precio['descuento'], 'fecha_inicio' => $promocion->fecha_inicio->toISOString(), 'fecha_fin' => $promocion->fecha_fin->toISOString()],
        ];
    }
}
