<?php

namespace App\Models;

use Database\Factories\ProductoVarianteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductoVariante extends Model
{
    /** @use HasFactory<ProductoVarianteFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['nombre', 'precio', 'stock'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function detallesPedido(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    public function detallesVenta(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'producto_variante_id');
    }

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'stock' => 'integer',
            'activo' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
