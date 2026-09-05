<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductoVariante> */
class ProductoVarianteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'nombre' => fake()->unique()->randomElement(['Pequeña', 'Mediana', 'Grande', 'Familiar', '6 unidades', '12 unidades']),
            'precio' => fake()->randomFloat(2, 1, 10000),
            'stock' => fake()->numberBetween(0, 100),
            'activo' => true,
        ];
    }
}
