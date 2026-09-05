<?php

namespace App\Http\Requests;

class UpdateRecetaRequest extends StoreRecetaRequest
{
    public function rules(): array
    {
        return [
            'producto_id' => ['sometimes', 'integer'],
            'nombre' => ['sometimes', 'string', 'max:160'],
            'rendimiento' => ['sometimes', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'ingredientes' => ['sometimes', 'array'],
            'ingredientes.*.ingrediente_id' => ['required', 'integer', 'distinct'],
            'ingredientes.*.cantidad' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
        ];
    }
}
