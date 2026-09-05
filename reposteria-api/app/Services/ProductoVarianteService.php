<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductoVarianteService
{
    public function __construct(private CatalogoAccessService $acceso) {}

    public function crear(User $actor, Producto $producto, array $datos): ProductoVariante
    {
        $this->autorizar($actor, $producto);
        $datos = $this->validar($datos, $producto);

        return $producto->variantes()->create($datos)->refresh();
    }

    public function actualizar(User $actor, ProductoVariante $variante, array $datos): ProductoVariante
    {
        $producto = $variante->producto;
        $this->autorizar($actor, $producto);
        $variante->update($this->validar($datos, $producto, $variante));

        return $variante->refresh();
    }

    public function establecerActiva(User $actor, ProductoVariante $variante, bool $activa): ProductoVariante
    {
        $this->autorizar($actor, $variante->producto);
        $variante->forceFill(['activo' => $activa])->save();

        return $variante->refresh();
    }

    public function eliminar(User $actor, ProductoVariante $variante): void
    {
        $this->autorizar($actor, $variante->producto);
        $variante->delete();
    }

    private function autorizar(User $actor, Producto $producto): void
    {
        if ($producto->trashed()) {
            throw new DomainException('No se administran variantes de un producto eliminado.');
        }

        if (! $producto->activo) {
            throw new DomainException('No se administran variantes de un producto inactivo.');
        }

        $this->acceso->autorizar($actor, $producto->reposteria);
    }

    private function validar(array $datos, Producto $producto, ?ProductoVariante $variante = null): array
    {
        return Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:120', Rule::unique('producto_variantes')->where('producto_id', $producto->id)->ignore($variante)],
            'precio' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'stock' => ['required', 'integer', 'min:0'],
        ])->validate();
    }
}
