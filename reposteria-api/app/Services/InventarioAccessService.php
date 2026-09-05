<?php

namespace App\Services;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class InventarioAccessService
{
    public function autorizarAdministracion(User $actor, Reposteria $reposteria): void
    {
        $this->validar($reposteria);
        if ($actor->esSuperadmin() || ($actor->role?->nombre === 'admin' && $actor->puedeOperarEnReposteria($reposteria))) {
            return;
        }
        throw new AuthorizationException('Solo un administrador autorizado puede gestionar inventario.');
    }

    public function autorizarSalida(User $actor, Reposteria $reposteria): void
    {
        $this->validar($reposteria);
        if ($actor->esSuperadmin() || (in_array($actor->role?->nombre, ['admin', 'produccion'], true) && $actor->puedeOperarEnReposteria($reposteria))) {
            return;
        }
        throw new AuthorizationException('No tiene autorización para registrar salidas.');
    }

    private function validar(Reposteria $reposteria): void
    {
        if ($reposteria->trashed() || $reposteria->estado !== ReposteriaEstado::Aprobada) {
            throw new DomainException('La repostería debe estar aprobada y activa.');
        }
    }
}
