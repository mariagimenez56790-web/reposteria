<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoReposteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('motivo')) {
            $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
        }
    }

    public function rules(): array
    {
        $requiereMotivo = in_array($this->route()?->getActionMethod(), ['rechazar', 'suspender'], true);

        return ['motivo' => [$requiereMotivo ? 'required' : 'nullable', 'string', 'max:4000']];
    }
}
