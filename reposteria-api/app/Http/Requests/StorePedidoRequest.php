<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['cliente_id' => ['nullable', 'integer'], 'fecha_entrega' => ['nullable', 'date'], 'observaciones' => ['nullable', 'string', 'max:4000'], 'detalles' => ['required', 'array', 'min:1'], 'detalles.*.producto_id' => ['required', 'integer'], 'detalles.*.producto_variante_id' => ['nullable', 'integer'], 'detalles.*.cantidad' => ['required', 'integer', 'min:1']];
    }
}
