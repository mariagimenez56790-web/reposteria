<?php

namespace App\Services;

use App\Enums\ReposteriaEstado;
use App\Models\Reposteria;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReposteriaUsuarioService
{
    private const ROLES_ASIGNABLES = ['admin', 'vendedor', 'produccion'];

    public function crear(User $actor, Reposteria $reposteria, array $datos): User
    {
        $this->autorizarGestion($actor, $reposteria);
        $datos = Validator::make($datos, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'rol' => ['required', Rule::in(self::ROLES_ASIGNABLES)],
        ])->validate();

        return DB::transaction(function () use ($actor, $reposteria, $datos): User {
            $rol = Role::query()->where('nombre', $datos['rol'])->firstOrFail();
            $usuario = new User;
            $usuario->forceFill(['role_id' => $rol->id, 'name' => $datos['name'], 'email' => mb_strtolower(trim($datos['email'])), 'password' => $datos['password'], 'activo' => true])->save();
            $this->asociar($actor, $reposteria, $usuario);

            return $usuario->refresh()->load('role');
        });
    }

    public function actualizar(User $actor, Reposteria $reposteria, User $usuario, array $datos): User
    {
        $this->autorizarGestion($actor, $reposteria);
        $this->validarMembresia($reposteria, $usuario);
        $datos = Validator::make($datos, [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario)],
            'activo' => ['sometimes', 'boolean'],
            'rol' => ['sometimes', Rule::in(self::ROLES_ASIGNABLES)],
        ])->validate();
        $desactiva = array_key_exists('activo', $datos) && ! $datos['activo'];
        $reduceAdmin = isset($datos['rol']) && $usuario->role?->nombre === 'admin' && $datos['rol'] !== 'admin';
        if (($desactiva || $reduceAdmin) && $actor->is($usuario)) {
            throw ValidationException::withMessages(['usuario' => 'No puede desactivarse ni reducir su propio rol administrativo.']);
        }
        if (($desactiva || $reduceAdmin) && $usuario->reposteriasComoPropietario()->exists()) {
            throw ValidationException::withMessages(['usuario' => 'El propietario debe permanecer como administrador activo.']);
        }
        if (($desactiva || $reduceAdmin) && $usuario->role?->nombre === 'admin') {
            $this->validarOtroAdministrador($reposteria, $usuario);
        }

        return DB::transaction(function () use ($usuario, $datos): User {
            $cambios = array_intersect_key($datos, array_flip(['name', 'email', 'activo']));
            if (isset($cambios['email'])) {
                $cambios['email'] = mb_strtolower(trim($cambios['email']));
            }
            if (isset($datos['rol'])) {
                $cambios['role_id'] = Role::query()->where('nombre', $datos['rol'])->firstOrFail()->id;
            }
            $usuario->forceFill($cambios)->save();

            return $usuario->refresh()->load('role');
        });
    }

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

        if ($actor->is($usuario)) {
            throw new DomainException('No puede retirar su propia membresía.');
        }

        if ($reposteria->propietario_id === $usuario->id) {
            throw new DomainException('No se puede retirar al propietario de su repostería.');
        }

        if ($usuario->activo && $usuario->role?->nombre === 'admin') {
            $this->validarOtroAdministrador($reposteria, $usuario);
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

    public function autorizarGestion(User $actor, Reposteria $reposteria): void
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

    private function validarMembresia(Reposteria $reposteria, User $usuario): void
    {
        if (! $reposteria->usuarios()->whereKey($usuario->id)->exists()) {
            throw new DomainException('El usuario no pertenece a esta repostería.');
        }
    }

    private function validarOtroAdministrador(Reposteria $reposteria, User $usuario): void
    {
        $existe = $reposteria->usuarios()->whereKeyNot($usuario->id)->where('activo', true)->whereHas('role', fn ($query) => $query->where('nombre', 'admin'))->exists();
        if (! $existe) {
            throw ValidationException::withMessages(['usuario' => 'La repostería debe conservar al menos otro administrador activo.']);
        }
    }
}
