<?php

namespace App\Services;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReposteriaEstadoService
{
    public function aprobar(Reposteria $reposteria, User $superadmin): Reposteria
    {
        $this->autorizar($superadmin);

        return $this->actualizar($reposteria, ReposteriaEstado::Pendiente, [
            'estado' => ReposteriaEstado::Aprobada,
            'aprobada_por' => $superadmin->id,
            'fecha_aprobacion' => now(),
            'motivo_estado' => null,
        ]);
    }

    public function rechazar(Reposteria $reposteria, User $superadmin, string $motivo): Reposteria
    {
        $this->autorizar($superadmin);
        $motivo = $this->motivoRequerido($motivo);

        return $this->actualizar($reposteria, ReposteriaEstado::Pendiente, [
            'estado' => ReposteriaEstado::Rechazada,
            'aprobada_por' => null,
            'fecha_aprobacion' => null,
            'motivo_estado' => $motivo,
        ]);
    }

    public function suspender(Reposteria $reposteria, User $superadmin, string $motivo): Reposteria
    {
        $this->autorizar($superadmin);
        $motivo = $this->motivoRequerido($motivo);

        return $this->actualizar($reposteria, ReposteriaEstado::Aprobada, [
            'estado' => ReposteriaEstado::Suspendida,
            'motivo_estado' => $motivo,
        ]);
    }

    public function inactivar(Reposteria $reposteria, User $superadmin, ?string $motivo = null): Reposteria
    {
        $this->autorizar($superadmin);

        if ($reposteria->estado === ReposteriaEstado::Inactiva) {
            throw new DomainException('La repostería ya está inactiva.');
        }

        return DB::transaction(function () use ($reposteria, $motivo): Reposteria {
            $actual = Reposteria::query()->lockForUpdate()->findOrFail($reposteria->id);

            if ($actual->estado === ReposteriaEstado::Inactiva) {
                throw new DomainException('La repostería ya está inactiva.');
            }

            $actual->forceFill([
                'estado' => ReposteriaEstado::Inactiva,
                'motivo_estado' => filled($motivo) ? trim($motivo) : null,
            ])->save();

            return $actual;
        });
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function actualizar(
        Reposteria $reposteria,
        ReposteriaEstado $estadoEsperado,
        array $cambios,
    ): Reposteria {
        return DB::transaction(function () use ($reposteria, $estadoEsperado, $cambios): Reposteria {
            $actual = Reposteria::query()->lockForUpdate()->findOrFail($reposteria->id);

            if ($actual->estado !== $estadoEsperado) {
                throw new DomainException(sprintf(
                    'No se permite cambiar una repostería de %s mediante esta acción.',
                    $actual->estado->value,
                ));
            }

            $actual->forceFill($cambios)->save();

            return $actual;
        });
    }

    public function autorizar(User $usuario): void
    {
        if (! $usuario->esSuperadmin()) {
            throw new AuthorizationException('Solo un superadmin activo puede administrar el estado.');
        }
    }

    private function motivoRequerido(string $motivo): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('El motivo es obligatorio para esta acción.');
        }

        return $motivo;
    }
}
