<?php

namespace App\Http\Requests;

class UpdateClienteRequest extends StoreClienteRequest
{
    public function rules(): array
    {
        return ['nombre' => ['sometimes', 'string', 'max:160'], 'telefono' => ['sometimes', 'nullable', 'string', 'max:30'], 'email' => ['sometimes', 'nullable', 'email', 'max:255'], 'direccion' => ['sometimes', 'nullable', 'string', 'max:255'], 'notas' => ['sometimes', 'nullable', 'string', 'max:3000']];
    }
}
