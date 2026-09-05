<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Reposteria;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;

class ClienteService
{
    public function __construct(private ClienteAccessService $acceso) {}

    public function listar(User $actor, Reposteria $reposteria): Collection
    {
        $this->acceso->autorizarLecturaEscritura($actor, $reposteria);

        return $reposteria->clientes()->orderBy('nombre')->get();
    }

    public function crear(User $actor, Reposteria $reposteria, array $datos): Cliente
    {
        $this->acceso->autorizarLecturaEscritura($actor, $reposteria);

        return $reposteria->clientes()->create($this->validar($datos))->refresh();
    }

    public function actualizar(User $actor, Cliente $cliente, array $datos): Cliente
    {
        $this->acceso->autorizarLecturaEscritura($actor, $cliente->reposteria);
        $cliente->update($this->validar($datos));

        return $cliente->refresh();
    }

    public function establecerActivo(User $actor, Cliente $cliente, bool $activo): Cliente
    {
        $this->acceso->autorizarAdministracion($actor, $cliente->reposteria);
        $cliente->forceFill(['activo' => $activo])->save();

        return $cliente->refresh();
    }

    public function eliminar(User $actor, Cliente $cliente): void
    {
        $this->acceso->autorizarAdministracion($actor, $cliente->reposteria);
        $cliente->delete();
    }

    private function validar(array $datos): array
    {
        $datos['email'] = filled($datos['email'] ?? null) ? mb_strtolower(trim($datos['email'])) : null;

        return Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:160'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\-\s]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:3000'],
        ])->validate();
    }
}
