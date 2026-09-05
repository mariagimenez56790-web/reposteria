<?php

namespace App\Models;

use Database\Factories\PedidoDetalleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoDetalle extends Model
{
    /** @use HasFactory<PedidoDetalleFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'producto_variante_id');
    }

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'precio_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }
}
