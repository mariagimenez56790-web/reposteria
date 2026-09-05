<?php

namespace Database\Factories;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PedidoDetalle> */
class PedidoDetalleFactory extends Factory
{
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 5);
        $precio = fake()->numberBetween(100, 10000) / 100;

        return ['pedido_id' => Pedido::factory(), 'producto_id' => Producto::factory(), 'producto_variante_id' => null, 'nombre_producto' => fake()->words(3, true), 'nombre_variante' => null, 'cantidad' => $cantidad, 'precio_unitario' => $precio, 'subtotal' => $precio * $cantidad];
    }

    public function para(Pedido $pedido, Producto $producto): static
    {
        return $this->state(fn () => ['pedido_id' => $pedido->id, 'producto_id' => $producto->id, 'nombre_producto' => $producto->nombre, 'precio_unitario' => $producto->precio]);
    }
}
