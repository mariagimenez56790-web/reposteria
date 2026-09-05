<?php

namespace App\Models;

use App\Enums\PromocionTipoDescuento;
use Database\Factories\PromocionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promocion extends Model
{
    /** @use HasFactory<PromocionFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'promociones';

    protected $guarded = ['reposteria_id', 'activo'];

    public function reposteria(): BelongsTo
    {
        return $this->belongsTo(Reposteria::class);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class)->withTimestamps();
    }

    public function variantes(): BelongsToMany
    {
        return $this->belongsToMany(ProductoVariante::class, 'producto_variante_promocion')->withTimestamps();
    }

    public function scopeVigentes(Builder $query, mixed $momento = null): Builder
    {
        $momento ??= now();

        return $query->where('activo', true)->where('fecha_inicio', '<=', $momento)->where('fecha_fin', '>=', $momento);
    }

    protected function casts(): array
    {
        return ['tipo_descuento' => PromocionTipoDescuento::class, 'valor_descuento' => 'decimal:2', 'fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime', 'activo' => 'boolean', 'deleted_at' => 'datetime'];
    }
}
