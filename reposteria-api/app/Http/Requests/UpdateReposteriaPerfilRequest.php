<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReposteriaPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $campos = ['nombre', 'descripcion', 'telefono', 'email', 'direccion', 'ciudad'];
        $normalizados = [];

        foreach ($campos as $campo) {
            if ($this->exists($campo) && is_string($this->input($campo))) {
                $normalizados[$campo] = trim($this->input($campo));
            }
        }

        $this->merge($normalizados);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ciudad' => ['sometimes', 'nullable', 'string', 'max:255'],
            'id' => ['prohibited'],
            'slug' => ['prohibited'],
            'logo' => ['prohibited'],
            'portada' => ['prohibited'],
            'estado' => ['prohibited'],
            'propietario_id' => ['prohibited'],
            'aprobada_por' => ['prohibited'],
            'fecha_aprobacion' => ['prohibited'],
            'motivo_estado' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }
}
