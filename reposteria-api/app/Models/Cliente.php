<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['nombre', 'telefono', 'email', 'direccion', 'notas'];

    public function reposteria(): BelongsTo
    {
        return $this->belongsTo(Reposteria::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function scopeDeReposteria(Builder $query, Reposteria|int $reposteria): Builder
    {
        return $query->where('reposteria_id', $reposteria instanceof Reposteria ? $reposteria->id : $reposteria);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'deleted_at' => 'datetime'];
    }
}
