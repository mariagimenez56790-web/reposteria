<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Reposteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cliente> */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reposteria_id' => Reposteria::factory(),
            'nombre' => fake()->name(),
            'telefono' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'direccion' => fake()->optional()->address(),
            'notas' => null,
            'activo' => true,
        ];
    }
}
