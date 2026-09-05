<?php

namespace Database\Factories;

use App\Enums\PedidoEstado;
use App\Models\Pedido;
use App\Models\Reposteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pedido> */
class PedidoFactory extends Factory
{
    public function definition(): array
    {
        return ['reposteria_id' => Reposteria::factory(), 'cliente_id' => null, 'estado' => PedidoEstado::Pendiente, 'fecha_pedido' => now(), 'fecha_entrega' => null, 'observaciones' => null, 'total' => 0];
    }
}
