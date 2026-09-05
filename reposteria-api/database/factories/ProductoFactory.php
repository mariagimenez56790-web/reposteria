<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Reposteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Producto> */
class ProductoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reposteria_id' => Reposteria::factory(),
            'categoria_id' => null,
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->sentence(),
            'precio' => fake()->randomFloat(2, 1, 10000),
            'imagen' => null,
            'personalizable' => false,
            'maneja_stock' => false,
            'stock' => 0,
            'activo' => true,
        ];
    }

    public function conCategoria(Categoria $categoria): static
    {
        return $this->state(fn () => [
            'reposteria_id' => $categoria->reposteria_id,
            'categoria_id' => $categoria->id,
        ]);
    }
}
