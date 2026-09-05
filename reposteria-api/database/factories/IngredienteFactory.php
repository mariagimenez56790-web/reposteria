<?php

namespace Database\Factories;

use App\Enums\UnidadMedida;
use App\Models\Ingrediente;
use App\Models\Reposteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ingrediente> */
class IngredienteFactory extends Factory
{
    public function definition(): array
    {
        return ['reposteria_id' => Reposteria::factory(), 'nombre' => fake()->unique()->words(2, true), 'unidad_medida' => fake()->randomElement(UnidadMedida::cases()), 'stock_actual' => '0.000', 'stock_minimo' => '0.000', 'costo_unitario' => null, 'activo' => true];
    }
}
