<?php

namespace App\Services;

use App\Enums\PromocionTipoDescuento;
use App\Enums\ReposteriaEstado;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Promocion;
use App\Models\Reposteria;
use App\Models\User;
use App\Support\Dinero;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromocionService
{
    public function crear(User $actor, Reposteria $reposteria, array $datos): Promocion
    {
        $this->autorizarAdministracion($actor, $reposteria);
        $promocion = new Promocion;
        $promocion->forceFill($this->validar($datos) + ['reposteria_id' => $reposteria->id, 'activo' => true])->save();

        return $promocion->refresh();
    }

    public function actualizar(User $actor, Promocion $promocion, array $datos): Promocion
    {
        $this->autorizarAdministracion($actor, $promocion->reposteria);
        $validados = $this->validar($datos);
        $this->validarMontoFijoEnAsociaciones($promocion, $validados);
        $promocion->forceFill($validados)->save();

        return $promocion->refresh();
    }

    public function establecerActiva(User $actor, Promocion $promocion, bool $activa): Promocion
    {
        $this->autorizarAdministracion($actor, $promocion->reposteria);
        $promocion->forceFill(['activo' => $activa])->save();

        return $promocion->refresh();
    }

    public function eliminar(User $actor, Promocion $promocion): void
    {
        $this->autorizarAdministracion($actor, $promocion->reposteria);
        $promocion->delete();
    }

    public function asociarProducto(User $actor, Promocion $promocion, Producto $producto): void
    {
        $this->autorizarAdministracion($actor, $promocion->reposteria);
        $this->validarProducto($promocion, $producto);
        $this->validarDescuentoAplicable($promocion, $producto->precio);
        if ($promocion->productos()->whereKey($producto->id)->exists()) {
            throw ValidationException::withMessages(['producto_id' => 'El producto ya está asociado.']);
        }
        $promocion->productos()->attach($producto->id);
    }

    public function quitarProducto(User $actor, Promocion $promocion, Producto $producto): void
    {
        $this->autorizarAdministracion($actor, $promocion->reposteria);
        $this->validarProducto($promocion, $producto, false);
        $promocion->productos()->detach($producto->id);
    }

    public function asociarVariante(User $actor, Promocion $promocion, ProductoVariante $variante): void
    {
        $this->autorizarAdministracion($actor, $promocion->reposteria);
        $this->validarVariante($promocion, $variante);
        $this->validarDescuentoAplicable($promocion, $variante->precio);
        if ($promocion->variantes()->whereKey($variante->id)->exists()) {
            throw ValidationException::withMessages(['producto_variante_id' => 'La variante ya está asociada.']);
        }
        $promocion->variantes()->attach($variante->id);
    }

    public function quitarVariante(User $actor, Promocion $promocion, ProductoVariante $variante): void
    {
        $this->autorizarAdministracion($actor, $promocion->reposteria);
        $this->validarVariante($promocion, $variante, false);
        $promocion->variantes()->detach($variante->id);
    }

    public function calcularPrecioPromocional(User $actor, Producto $producto, ?ProductoVariante $variante = null): array
    {
        $this->autorizarConsulta($actor, $producto->reposteria);
        if ($producto->trashed() || ! $producto->activo) {
            return $this->sinPromocion($variante?->precio ?? $producto->precio);
        }
        if ($variante !== null && ($variante->producto_id !== $producto->id || $variante->trashed() || ! $variante->activo)) {
            return $this->sinPromocion($variante->precio);
        }

        $precioBase = $variante?->precio ?? $producto->precio;
        $promociones = $variante === null ? collect() : $variante->promociones()->vigentes()->get();
        if ($promociones->isEmpty()) {
            $promociones = $producto->promociones()->vigentes()->get();
        }

        return $this->mejorPrecio($precioBase, $promociones);
    }

    private function mejorPrecio(string $precioBase, Collection $promociones): array
    {
        $base = Dinero::aCentavos($precioBase);
        $opciones = $promociones->map(function (Promocion $promocion) use ($base) {
            $descuento = $promocion->tipo_descuento === PromocionTipoDescuento::Porcentaje
                ? intdiv(($base * Dinero::aCentavos($promocion->valor_descuento)) + 5000, 10000)
                : Dinero::aCentavos($promocion->valor_descuento);

            return $descuento > $base ? null : ['promocion' => $promocion, 'descuento' => $descuento, 'final' => $base - $descuento];
        })->filter()->sortBy(fn (array $opcion) => [$opcion['final'], $opcion['promocion']->id])->first();

        if ($opciones === null) {
            return $this->sinPromocion($precioBase);
        }

        return ['precio_base' => Dinero::formatear($base), 'promocion_id' => $opciones['promocion']->id, 'descuento' => Dinero::formatear($opciones['descuento']), 'precio_final' => Dinero::formatear($opciones['final'])];
    }

    private function sinPromocion(string $precio): array
    {
        $base = Dinero::aCentavos($precio);

        return ['precio_base' => Dinero::formatear($base), 'promocion_id' => null, 'descuento' => '0.00', 'precio_final' => Dinero::formatear($base)];
    }

    private function validar(array $datos): array
    {
        return Validator::make($datos, ['nombre' => ['required', 'string', 'max:160'], 'descripcion' => ['nullable', 'string', 'max:4000'], 'tipo_descuento' => ['required', Rule::enum(PromocionTipoDescuento::class)], 'valor_descuento' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'], 'fecha_inicio' => ['required', 'date'], 'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio']])->after(function ($validator) use ($datos) {
            if (($datos['tipo_descuento'] ?? null) === PromocionTipoDescuento::Porcentaje->value && Dinero::aCentavos((string) ($datos['valor_descuento'] ?? 0)) > 10000) {
                $validator->errors()->add('valor_descuento', 'El porcentaje no puede superar 100.');
            }
        })->validate();
    }

    private function validarProducto(Promocion $promocion, Producto $producto, bool $operativo = true): void
    {
        if ($producto->reposteria_id !== $promocion->reposteria_id || ($operativo && ($producto->trashed() || ! $producto->activo))) {
            throw ValidationException::withMessages(['producto_id' => 'El producto no es válido para esta promoción.']);
        }
    }

    private function validarVariante(Promocion $promocion, ProductoVariante $variante, bool $operativa = true): void
    {
        $variante->loadMissing('producto');
        if ($variante->producto->reposteria_id !== $promocion->reposteria_id || ($operativa && ($variante->trashed() || ! $variante->activo || $variante->producto->trashed() || ! $variante->producto->activo))) {
            throw ValidationException::withMessages(['producto_variante_id' => 'La variante no es válida para esta promoción.']);
        }
    }

    private function validarDescuentoAplicable(Promocion $promocion, string $precio): void
    {
        if ($promocion->tipo_descuento === PromocionTipoDescuento::MontoFijo && Dinero::aCentavos($promocion->valor_descuento) > Dinero::aCentavos($precio)) {
            throw ValidationException::withMessages(['valor_descuento' => 'El descuento fijo supera el precio.']);
        }
    }

    private function validarMontoFijoEnAsociaciones(Promocion $promocion, array $datos): void
    {
        if ($datos['tipo_descuento'] !== PromocionTipoDescuento::MontoFijo->value) {
            return;
        }
        $descuento = Dinero::aCentavos((string) $datos['valor_descuento']);
        $invalido = $promocion->productos()->get()->contains(fn ($producto) => $descuento > Dinero::aCentavos($producto->precio))
            || $promocion->variantes()->get()->contains(fn ($variante) => $descuento > Dinero::aCentavos($variante->precio));
        if ($invalido) {
            throw ValidationException::withMessages(['valor_descuento' => 'El descuento fijo supera el precio de una asociación.']);
        }
    }

    private function autorizarAdministracion(User $actor, Reposteria $reposteria): void
    {
        $this->validarReposteria($reposteria);
        if ($actor->esSuperadmin() || ($actor->role?->nombre === 'admin' && $actor->puedeOperarEnReposteria($reposteria))) {
            return;
        }
        throw new AuthorizationException('No tiene autorización para administrar promociones.');
    }

    private function autorizarConsulta(User $actor, Reposteria $reposteria): void
    {
        $this->validarReposteria($reposteria);
        if ($actor->esSuperadmin() || (in_array($actor->role?->nombre, ['admin', 'vendedor'], true) && $actor->puedeOperarEnReposteria($reposteria))) {
            return;
        }
        throw new AuthorizationException('No tiene autorización para consultar promociones.');
    }

    private function validarReposteria(Reposteria $reposteria): void
    {
        if ($reposteria->trashed() || $reposteria->estado !== ReposteriaEstado::Aprobada) {
            throw new DomainException('La repostería debe estar aprobada y activa.');
        }
    }
}
