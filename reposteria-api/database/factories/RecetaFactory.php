<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Receta;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Receta> */
class RecetaFactory extends Factory
{
    public function definition(): array
    {
        return ['producto_id' => Producto::factory(), 'reposteria_id' => fn (array $attributes) => Producto::query()->findOrFail($attributes['producto_id'])->reposteria_id, 'nombre' => fake()->words(2, true), 'rendimiento' => '1.000', 'activo' => true];
    }
}
