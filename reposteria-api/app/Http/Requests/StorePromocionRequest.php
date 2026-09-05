<?php

namespace App\Http\Requests;

use App\Enums\PromocionTipoDescuento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromocionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:4000'],
            'tipo_descuento' => ['required', Rule::enum(PromocionTipoDescuento::class)],
            'valor_descuento' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'producto_ids' => ['sometimes', 'array'],
            'producto_ids.*' => ['integer', 'distinct'],
            'variante_ids' => ['sometimes', 'array'],
            'variante_ids.*' => ['integer', 'distinct'],
        ];
    }
}
