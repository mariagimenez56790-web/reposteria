<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Reposteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Categoria> */
class CategoriaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reposteria_id' => Reposteria::factory(),
            'nombre' => fake()->unique()->words(2, true),
            'descripcion' => fake()->sentence(),
            'activo' => true,
        ];
    }
}
