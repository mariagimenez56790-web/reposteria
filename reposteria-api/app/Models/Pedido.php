<?php

namespace App\Models;

use App\Enums\PedidoEstado;
use Database\Factories\PedidoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pedido extends Model
{
    /** @use HasFactory<PedidoFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['fecha_entrega', 'observaciones'];

    public function reposteria(): BelongsTo
    {
        return $this->belongsTo(Reposteria::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    protected function casts(): array
    {
        return ['estado' => PedidoEstado::class, 'fecha_pedido' => 'datetime', 'fecha_entrega' => 'datetime', 'total' => 'decimal:2', 'deleted_at' => 'datetime'];
    }
}
