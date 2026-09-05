<?php

namespace App\Services;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReposteriaUsuarioService
{
    public function asociar(User $actor, Reposteria $reposteria, User $usuario): void
    {
        $this->autorizarGestion($actor, $reposteria);
        $this->validarAsociacion($reposteria, $usuario);

        DB::transaction(function () use ($reposteria, $usuario): void {
            $reposteria = Reposteria::query()->lockForUpdate()->findOrFail($reposteria->id);

            if ($reposteria->usuarios()->whereKey($usuario->id)->exists()) {
                throw new DomainException('El usuario ya pertenece a esta repostería.');
            }

            $reposteria->usuarios()->attach($usuario->id);
        });
    }

    public function retirar(User $actor, Reposteria $reposteria, User $usuario): void
    {
        $this->autorizarGestion($actor, $reposteria);

        if ($reposteria->propietario_id === $usuario->id) {
            throw new DomainException('No se puede retirar al propietario de su repostería.');
        }

        DB::transaction(function () use ($reposteria, $usuario): void {
            $reposteria = Reposteria::query()->lockForUpdate()->findOrFail($reposteria->id);

            if (! $reposteria->usuarios()->whereKey($usuario->id)->exists()) {
                throw new DomainException('El usuario no pertenece a esta repostería.');
            }

            $reposteria->usuarios()->detach($usuario->id);
        });
    }

    public function pertenece(User $usuario, Reposteria $reposteria): bool
    {
        return $usuario->perteneceAReposteria($reposteria);
    }

    public function puedeOperar(User $usuario, Reposteria $reposteria): bool
    {
        return $usuario->puedeOperarEnReposteria($reposteria);
    }

    private function validarAsociacion(Reposteria $reposteria, User $usuario): void
    {
        if ($reposteria->trashed()) {
            throw new DomainException('No se pueden asociar usuarios a una repostería eliminada.');
        }

        if ($reposteria->estado !== ReposteriaEstado::Aprobada) {
            throw new DomainException('La repostería debe estar aprobada para asociar trabajadores.');
        }

        if (! $usuario->activo) {
            throw new DomainException('No se puede asociar un usuario inactivo.');
        }

        if (! $usuario->tieneRolInterno()) {
            throw new DomainException('El usuario no tiene un rol interno permitido.');
        }
    }

    private function autorizarGestion(User $actor, Reposteria $reposteria): void
    {
        if ($actor->esSuperadmin()) {
            return;
        }

        if (
            $actor->activo
            && $actor->role?->nombre === 'admin'
            && $actor->puedeOperarEnReposteria($reposteria)
        ) {
            return;
        }

        throw new AuthorizationException('No tiene autorización para gestionar esta membresía.');
    }
}
