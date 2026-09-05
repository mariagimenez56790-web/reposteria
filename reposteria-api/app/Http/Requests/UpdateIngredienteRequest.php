<?php

namespace App\Http\Requests;

use App\Enums\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngredienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:160'],
            'unidad_medida' => ['sometimes', Rule::enum(UnidadMedida::class)],
            'stock_minimo' => ['sometimes', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999999.999'],
            'costo_unitario' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }
}
