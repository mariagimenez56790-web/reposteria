<?php

namespace App\Http\Requests;

use App\Enums\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'metodo' => ['required', Rule::enum(MetodoPago::class)],
            'monto' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
