<?php

namespace Database\Factories;

use App\Enums\MovimientoInventarioTipo;
use App\Models\Ingrediente;
use App\Models\MovimientoInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MovimientoInventario> */
class MovimientoInventarioFactory extends Factory
{
    public function definition(): array
    {
        return ['ingrediente_id' => Ingrediente::factory(), 'reposteria_id' => fn (array $attributes) => Ingrediente::query()->findOrFail($attributes['ingrediente_id'])->reposteria_id, 'tipo' => MovimientoInventarioTipo::Entrada, 'cantidad' => '1.000', 'stock_anterior' => '0.000', 'stock_nuevo' => '1.000', 'motivo' => null, 'referencia_tipo' => null, 'referencia_id' => null, 'creado_por' => null, 'fecha_movimiento' => now()];
    }
}
