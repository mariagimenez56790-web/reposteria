<?php

namespace App\Models;

use App\Enums\UnidadMedida;
use Database\Factories\IngredienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingrediente extends Model
{
    /** @use HasFactory<IngredienteFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['reposteria_id', 'stock_actual'];

    public function reposteria(): BelongsTo
    {
        return $this->belongsTo(Reposteria::class);
    }

    public function recetas(): BelongsToMany
    {
        return $this->belongsToMany(Receta::class)->withPivot('cantidad')->withTimestamps();
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    protected function casts(): array
    {
        return ['unidad_medida' => UnidadMedida::class, 'stock_actual' => 'decimal:3', 'stock_minimo' => 'decimal:3', 'costo_unitario' => 'decimal:2', 'activo' => 'boolean', 'deleted_at' => 'datetime'];
    }
}
