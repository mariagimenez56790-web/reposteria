<?php

namespace Database\Factories;

use App\Enums\PromocionTipoDescuento;
use App\Models\Promocion;
use App\Models\Reposteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Promocion> */
class PromocionFactory extends Factory
{
    public function definition(): array
    {
        return ['reposteria_id' => Reposteria::factory(), 'nombre' => fake()->words(3, true), 'descripcion' => null, 'tipo_descuento' => PromocionTipoDescuento::Porcentaje, 'valor_descuento' => '10.00', 'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDay(), 'activo' => true];
    }

    public function porcentaje(string $valor = '10.00'): static
    {
        return $this->state(fn () => ['tipo_descuento' => PromocionTipoDescuento::Porcentaje, 'valor_descuento' => $valor]);
    }

    public function montoFijo(string $valor = '10.00'): static
    {
        return $this->state(fn () => ['tipo_descuento' => PromocionTipoDescuento::MontoFijo, 'valor_descuento' => $valor]);
    }

    public function vencida(): static
    {
        return $this->state(fn () => ['fecha_inicio' => now()->subDays(2), 'fecha_fin' => now()->subDay()]);
    }

    public function futura(): static
    {
        return $this->state(fn () => ['fecha_inicio' => now()->addDay(), 'fecha_fin' => now()->addDays(2)]);
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
