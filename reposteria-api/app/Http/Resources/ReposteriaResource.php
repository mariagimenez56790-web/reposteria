<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReposteriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'nombre' => $this->nombre, 'slug' => $this->slug, 'estado' => $this->estado->value];
    }
}
