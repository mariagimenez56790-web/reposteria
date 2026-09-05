<?php

namespace App\Services;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class CatalogoAccessService
{
    public function autorizar(User $actor, Reposteria $reposteria): void
    {
        if ($reposteria->trashed() || $reposteria->estado !== ReposteriaEstado::Aprobada) {
            throw new DomainException('La repostería debe estar aprobada y activa para administrar su catálogo.');
        }

        if ($actor->esSuperadmin()) {
            return;
        }

        if ($actor->role?->nombre === 'admin' && $actor->puedeOperarEnReposteria($reposteria)) {
            return;
        }

        throw new AuthorizationException('No tiene autorización para administrar este catálogo.');
    }
}
