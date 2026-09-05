<?php

namespace App\Http\Requests;

use App\Enums\MovimientoInventarioTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovimientoInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ingrediente_id' => ['required', 'integer'],
            'tipo' => ['required', Rule::enum(MovimientoInventarioTipo::class)],
            'cantidad' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'referencia_tipo' => ['nullable', 'string', 'max:80'],
            'referencia_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
