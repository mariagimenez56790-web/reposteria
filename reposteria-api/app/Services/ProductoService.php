<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Reposteria;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductoService
{
    public function __construct(private CatalogoAccessService $acceso) {}

    public function crear(User $actor, Reposteria $reposteria, array $datos): Producto
    {
        $this->acceso->autorizar($actor, $reposteria);

        return $this->guardar(new Producto, $reposteria, $datos);
    }

    public function actualizar(User $actor, Producto $producto, array $datos): Producto
    {
        $this->acceso->autorizar($actor, $producto->reposteria);

        return $this->guardar($producto, $producto->reposteria, $datos);
    }

    public function establecerActivo(User $actor, Producto $producto, bool $activo): Producto
    {
        $this->acceso->autorizar($actor, $producto->reposteria);
        $producto->forceFill(['activo' => $activo])->save();

        return $producto->refresh();
    }

    public function eliminar(User $actor, Producto $producto): void
    {
        $this->acceso->autorizar($actor, $producto->reposteria);
        $producto->delete();
    }

    private function guardar(Producto $producto, Reposteria $reposteria, array $datos): Producto
    {
        $datos = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:4000'],
            'precio' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'imagen' => ['nullable', 'string', 'max:255'],
            'personalizable' => ['required', 'boolean'],
            'maneja_stock' => ['required', 'boolean'],
            'stock' => ['required', 'integer', 'min:0'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
        ])->validate();

        $categoriaId = $datos['categoria_id'] ?? null;
        unset($datos['categoria_id']);

        if ($categoriaId !== null) {
            $categoria = Categoria::query()->findOrFail($categoriaId);
            if ($categoria->reposteria_id !== $reposteria->id) {
                throw ValidationException::withMessages(['categoria_id' => 'La categoría no pertenece a la repostería.']);
            }
        }

        $producto->forceFill($datos + [
            'reposteria_id' => $reposteria->id,
            'categoria_id' => $categoriaId,
        ])->save();

        return $producto->refresh();
    }
}
