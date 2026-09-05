<?php

namespace App\Http\Requests;

use App\Enums\PedidoEstado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarPedidoEstadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['estado' => ['required', Rule::enum(PedidoEstado::class)]];
    }
}
