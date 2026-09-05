<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePedidoDetalleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('variante_id') && ! $this->has('producto_variante_id')) {
            $this->merge(['producto_variante_id' => $this->input('variante_id')]);
        }
    }

    public function rules(): array
    {
        return ['producto_id' => ['required', 'integer'], 'producto_variante_id' => ['nullable', 'integer'], 'cantidad' => ['required', 'integer', 'min:1']];
    }
}
