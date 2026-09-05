<?php

namespace App\Services;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class ReposteriaPerfilService
{
    public function autorizar(User $actor, Reposteria $reposteria): void
    {
        if ($actor->esSuperadmin()) {
            return;
        }

        if ($reposteria->estado !== ReposteriaEstado::Aprobada || $reposteria->trashed()) {
            throw new DomainException('La repostería debe estar aprobada y activa para administrar su perfil.');
        }

        if ($actor->role?->nombre === 'admin' && $actor->puedeOperarEnReposteria($reposteria)) {
            return;
        }

        throw new AuthorizationException('No tiene autorización para administrar el perfil de esta repostería.');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(User $actor, Reposteria $reposteria, array $datos): Reposteria
    {
        $this->autorizar($actor, $reposteria);

        $reposteria->fill($datos)->save();

        return $reposteria->refresh();
    }
}
