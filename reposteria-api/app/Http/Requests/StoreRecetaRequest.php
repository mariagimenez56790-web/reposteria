<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'max:160'],
            'rendimiento' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'ingredientes' => ['sometimes', 'array'],
            'ingredientes.*.ingrediente_id' => ['required', 'integer', 'distinct'],
            'ingredientes.*.cantidad' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
        ];
    }
}
