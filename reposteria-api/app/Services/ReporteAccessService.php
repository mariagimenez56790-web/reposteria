<?php

namespace App\Services;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class ReporteAccessService
{
    public function comercial(User $actor, Reposteria $reposteria): void
    {
        $this->autorizar($actor, $reposteria, ['admin', 'vendedor']);
    }

    public function pedidos(User $actor, Reposteria $reposteria): void
    {
        $this->autorizar($actor, $reposteria, ['admin', 'vendedor', 'produccion']);
    }

    public function inventario(User $actor, Reposteria $reposteria): void
    {
        $this->autorizar($actor, $reposteria, ['admin', 'produccion']);
    }

    private function autorizar(User $actor, Reposteria $reposteria, array $roles): void
    {
        if ($actor->esSuperadmin()) {
            return;
        }
        if ($reposteria->estado !== ReposteriaEstado::Aprobada || $reposteria->trashed()) {
            throw new DomainException('La repostería debe estar aprobada y activa.');
        }
        if (in_array($actor->role?->nombre, $roles, true) && $actor->puedeOperarEnReposteria($reposteria)) {
            return;
        }
        throw new AuthorizationException('No tiene autorización para consultar este reporte.');
    }
}
