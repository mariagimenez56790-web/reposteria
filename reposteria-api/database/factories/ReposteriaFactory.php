<?php

namespace Database\Factories;

use App\Models\Reposteria;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reposteria>
 */
class ReposteriaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'propietario_id' => User::factory()->conRol('admin'),
            'nombre' => fake()->unique()->company(),
            'descripcion' => fake()->sentence(),
            'telefono' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'direccion' => fake()->address(),
            'ciudad' => fake()->city(),
        ];
    }
}
