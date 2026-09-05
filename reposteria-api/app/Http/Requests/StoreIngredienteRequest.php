<?php

namespace App\Http\Requests;

use App\Enums\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngredienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:160'],
            'unidad_medida' => ['required', Rule::enum(UnidadMedida::class)],
            'stock_minimo' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999999.999'],
            'costo_unitario' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }
}
