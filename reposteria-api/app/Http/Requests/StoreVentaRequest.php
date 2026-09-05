<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['nullable', 'integer'],
            'descuento' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'observaciones' => ['nullable', 'string', 'max:4000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer'],
            'detalles.*.producto_variante_id' => ['nullable', 'integer'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
