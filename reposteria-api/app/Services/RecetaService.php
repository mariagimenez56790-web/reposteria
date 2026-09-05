<?php

namespace App\Services;

use App\Models\Ingrediente;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecetaService
{
    public function __construct(private InventarioAccessService $acceso) {}

    public function crear(User $actor, Reposteria $reposteria, array $datos): Receta
    {
        $this->acceso->autorizarAdministracion($actor, $reposteria);
        $datos = $this->validar($datos, $reposteria);
        $producto = Producto::query()->findOrFail($datos['producto_id']);
        if ($producto->reposteria_id !== $reposteria->id || $producto->trashed() || ! $producto->activo) {
            throw ValidationException::withMessages(['producto_id' => 'El producto no pertenece a la repostería.']);
        }
        $receta = new Receta;
        $receta->forceFill($datos + ['reposteria_id' => $reposteria->id, 'activo' => true])->save();

        return $receta->refresh();
    }

    public function actualizar(User $actor, Receta $receta, array $datos): Receta
    {
        $this->acceso->autorizarAdministracion($actor, $receta->reposteria);
        $datos = $this->validar(array_replace([
            'producto_id' => $receta->producto_id,
            'nombre' => $receta->nombre,
            'rendimiento' => $receta->rendimiento,
        ], $datos), $receta->reposteria, $receta);
        if ((int) $datos['producto_id'] !== $receta->producto_id) {
            throw new DomainException('No puede cambiarse el producto de una receta.');
        }
        $receta->forceFill($datos)->save();

        return $receta->refresh();
    }

    public function crearCompleta(User $actor, Reposteria $reposteria, array $datos, array $ingredientes): Receta
    {
        return DB::transaction(function () use ($actor, $reposteria, $datos, $ingredientes): Receta {
            $receta = $this->crear($actor, $reposteria, $datos);
            $this->sincronizarIngredientes($actor, $receta, $ingredientes);

            return $receta->refresh()->load(['producto', 'ingredientes']);
        });
    }

    public function actualizarCompleta(User $actor, Receta $receta, array $datos, ?array $ingredientes): Receta
    {
        return DB::transaction(function () use ($actor, $receta, $datos, $ingredientes): Receta {
            $receta = $this->actualizar($actor, $receta, $datos);
            if ($ingredientes !== null) {
                $this->sincronizarIngredientes($actor, $receta, $ingredientes);
            }

            return $receta->refresh()->load(['producto', 'ingredientes']);
        });
    }

    public function guardarIngrediente(User $actor, Receta $receta, Ingrediente $ingrediente, string|int $cantidad): Receta
    {
        $this->acceso->autorizarAdministracion($actor, $receta->reposteria);
        if ($ingrediente->reposteria_id !== $receta->reposteria_id || $ingrediente->trashed() || ! $ingrediente->activo) {
            throw ValidationException::withMessages(['ingrediente_id' => 'El ingrediente no pertenece a la repostería.']);
        }
        $datos = Validator::make(['cantidad' => $cantidad], ['cantidad' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999']])->validate();
        $receta->ingredientes()->syncWithoutDetaching([$ingrediente->id => ['cantidad' => $datos['cantidad']]]);

        return $receta->refresh();
    }

    public function quitarIngrediente(User $actor, Receta $receta, Ingrediente $ingrediente): Receta
    {
        $this->acceso->autorizarAdministracion($actor, $receta->reposteria);
        $receta->ingredientes()->detach($ingrediente->id);

        return $receta->refresh();
    }

    public function establecerActiva(User $actor, Receta $receta, bool $activa): Receta
    {
        $this->acceso->autorizarAdministracion($actor, $receta->reposteria);
        $receta->forceFill(['activo' => $activa])->save();

        return $receta->refresh();
    }

    public function eliminar(User $actor, Receta $receta): void
    {
        $this->acceso->autorizarAdministracion($actor, $receta->reposteria);
        $receta->delete();
    }

    private function validar(array $datos, Reposteria $reposteria, ?Receta $receta = null): array
    {
        return Validator::make($datos, ['producto_id' => ['required', 'integer', 'exists:productos,id'], 'nombre' => ['required', 'string', 'max:160', Rule::unique('recetas')->where(fn ($query) => $query->where('reposteria_id', $reposteria->id)->where('producto_id', $datos['producto_id'] ?? 0))->ignore($receta)], 'rendimiento' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999']])->validate();
    }

    private function sincronizarIngredientes(User $actor, Receta $receta, array $ingredientes): void
    {
        $receta->ingredientes()->detach();
        foreach ($ingredientes as $datos) {
            $ingrediente = Ingrediente::query()->findOrFail($datos['ingrediente_id']);
            $this->guardarIngrediente($actor, $receta, $ingrediente, $datos['cantidad']);
        }
    }
}
