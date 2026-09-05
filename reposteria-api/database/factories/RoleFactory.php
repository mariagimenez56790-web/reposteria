<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->slug(2),
            'descripcion' => fake()->sentence(),
            'ambito' => 'reposteria',
        ];
    }
}
