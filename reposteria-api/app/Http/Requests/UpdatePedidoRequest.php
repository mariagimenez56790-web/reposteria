<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['cliente_id' => ['sometimes', 'nullable', 'integer'], 'fecha_entrega' => ['sometimes', 'nullable', 'date'], 'observaciones' => ['sometimes', 'nullable', 'string', 'max:4000']];
    }
}
