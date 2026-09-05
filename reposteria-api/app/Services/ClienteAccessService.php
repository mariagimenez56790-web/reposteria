<?php

namespace App\Services;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class ClienteAccessService
{
    public function autorizarLecturaEscritura(User $actor, Reposteria $reposteria): void
    {
        $this->validarReposteria($reposteria);

        if ($actor->esSuperadmin()) {
            return;
        }

        if (in_array($actor->role?->nombre, ['admin', 'vendedor'], true) && $actor->puedeOperarEnReposteria($reposteria)) {
            return;
        }

        throw new AuthorizationException('No tiene autorización para administrar clientes de esta repostería.');
    }

    public function autorizarAdministracion(User $actor, Reposteria $reposteria): void
    {
        $this->validarReposteria($reposteria);

        if ($actor->esSuperadmin()) {
            return;
        }

        if ($actor->role?->nombre === 'admin' && $actor->puedeOperarEnReposteria($reposteria)) {
            return;
        }

        throw new AuthorizationException('Solo un administrador autorizado puede realizar esta acción.');
    }

    private function validarReposteria(Reposteria $reposteria): void
    {
        if ($reposteria->trashed() || $reposteria->estado !== ReposteriaEstado::Aprobada) {
            throw new DomainException('La repostería debe estar aprobada y activa.');
        }
    }
}
