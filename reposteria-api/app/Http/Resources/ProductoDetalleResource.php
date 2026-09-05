<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ProductoDetalleResource extends ProductoResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['variantes' => ProductoVarianteResource::collection($this->whenLoaded('variantes'))->resolve($request)];
    }
}
