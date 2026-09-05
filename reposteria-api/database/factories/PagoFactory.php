<?php

namespace Database\Factories;

use App\Enums\MetodoPago;
use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pago> */
class PagoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'venta_id' => Venta::factory(),
            'metodo' => fake()->randomElement(MetodoPago::cases()),
            'monto' => fake()->randomFloat(2, 1, 100),
            'fecha_pago' => now(),
            'referencia' => null,
            'observaciones' => null,
        ];
    }
}
