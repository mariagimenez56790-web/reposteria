<?php

namespace App\Services;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CatalogoConsultaService
{
    public function reposterias(User $actor, int $porPagina): LengthAwarePaginator
    {
        if ($actor->esSuperadmin()) {
            return Reposteria::query()->where('estado', ReposteriaEstado::Aprobada)->orderBy('nombre')->paginate($porPagina);
        }
        if (! $actor->activo || ! in_array($actor->role?->nombre, ['admin', 'vendedor', 'produccion'], true)) {
            throw new AuthorizationException('No tiene acceso al catálogo interno.');
        }

        return $actor->reposterias()->where('estado', ReposteriaEstado::Aprobada)->orderBy('nombre')->paginate($porPagina);
    }

    public function autorizar(User $actor, Reposteria $reposteria): void
    {
        if ($reposteria->estado !== ReposteriaEstado::Aprobada || $reposteria->trashed()) {
            throw new AuthorizationException('La repostería no está disponible.');
        }
        if ($actor->esSuperadmin()) {
            return;
        }
        if ($actor->activo && in_array($actor->role?->nombre, ['admin', 'vendedor', 'produccion'], true) && $actor->perteneceAReposteria($reposteria)) {
            return;
        }

        throw new AuthorizationException('No tiene acceso a esta repostería.');
    }
}
