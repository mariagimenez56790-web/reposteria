<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReposteriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'logo' => $this->logo,
            'portada' => $this->portada,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'estado' => $this->estado->value,
            'propietario' => $this->whenLoaded('propietario', fn () => [
                'id' => $this->propietario->id,
                'nombre' => $this->propietario->name,
                'email' => $this->propietario->email,
                'activo' => $this->propietario->activo,
            ]),
            'aprobada_por' => $this->whenLoaded('aprobadaPor', fn () => $this->aprobadaPor === null ? null : [
                'id' => $this->aprobadaPor->id,
                'nombre' => $this->aprobadaPor->name,
            ]),
            'fecha_aprobacion' => $this->fecha_aprobacion?->toISOString(),
            'motivo_estado' => $this->motivo_estado,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
