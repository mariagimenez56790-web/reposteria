<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'metodo' => $this->metodo->value,
            'monto' => $this->monto,
            'fecha_pago' => $this->fecha_pago->toISOString(),
            'referencia' => $this->referencia,
            'observaciones' => $this->observaciones,
        ];
    }
}
