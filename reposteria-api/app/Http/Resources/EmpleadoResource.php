<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpleadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'email' => $this->email,
            'rol' => $this->role?->nombre,
            'activo' => $this->activo,
            'es_propietario' => isset($this->reposteria_consultada_id) && (int) $this->reposteria_consultada_id === (int) $this->id,
            'miembro_desde' => $this->pivot?->created_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
