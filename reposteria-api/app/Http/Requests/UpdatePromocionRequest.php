<?php

namespace App\Http\Requests;

use App\Enums\PromocionTipoDescuento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromocionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:160'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'tipo_descuento' => ['sometimes', Rule::enum(PromocionTipoDescuento::class)],
            'valor_descuento' => ['sometimes', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'fecha_inicio' => ['sometimes', 'date'],
            'fecha_fin' => ['sometimes', 'date'],
            'activo' => ['sometimes', 'boolean'],
            'producto_ids' => ['sometimes', 'array'],
            'producto_ids.*' => ['integer', 'distinct'],
            'variante_ids' => ['sometimes', 'array'],
            'variante_ids.*' => ['integer', 'distinct'],
        ];
    }
}
