<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VentaDetalle> */
class VentaDetalleFactory extends Factory
{
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 5);
        $precio = fake()->numberBetween(100, 10000) / 100;

        return [
            'venta_id' => Venta::factory(),
            'producto_id' => function (array $attributes) {
                $venta = Venta::query()->findOrFail($attributes['venta_id']);

                return Producto::factory()->create([
                    'reposteria_id' => $venta->reposteria_id,
                ])->id;
            },
            'producto_variante_id' => null,
            'nombre_producto' => fake()->words(3, true),
            'nombre_variante' => null,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $precio * $cantidad,
        ];
    }

    public function para(Venta $venta, Producto $producto): static
    {
        return $this->state(fn () => [
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'nombre_producto' => $producto->nombre,
            'precio_unitario' => $producto->precio,
        ]);
    }
}
