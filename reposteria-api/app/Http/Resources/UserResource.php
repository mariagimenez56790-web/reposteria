<?php

namespace App\Http\Resources;

use App\Enums\ReposteriaEstado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reposterias = $this->esSuperadmin()
            ? collect()
            : $this->reposterias()->where('estado', ReposteriaEstado::Aprobada->value)->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->nombre,
            'activo' => $this->activo,
            'reposterias' => ReposteriaResource::collection($reposterias)->resolve($request),
        ];
    }
}
