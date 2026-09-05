<?php

namespace Database\Factories;

use App\Enums\VentaEstado;
use App\Models\Reposteria;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Venta> */
class VentaFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 1, 10000);

        return [
            'reposteria_id' => Reposteria::factory(),
            'pedido_id' => null,
            'cliente_id' => null,
            'estado' => VentaEstado::Pendiente,
            'fecha_venta' => now(),
            'subtotal' => $total,
            'descuento' => 0,
            'total' => $total,
            'monto_pagado' => 0,
            'saldo' => $total,
            'observaciones' => null,
        ];
    }
}
