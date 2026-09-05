<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['nombre' => ['required', 'string', 'max:160'], 'telefono' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'direccion' => ['nullable', 'string', 'max:255'], 'notas' => ['nullable', 'string', 'max:3000']];
    }
}
