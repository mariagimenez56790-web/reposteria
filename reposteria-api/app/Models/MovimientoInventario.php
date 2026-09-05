<?php

namespace App\Models;

use App\Enums\MovimientoInventarioTipo;
use Database\Factories\MovimientoInventarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    /** @use HasFactory<MovimientoInventarioFactory> */
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $guarded = ['*'];

    public function reposteria(): BelongsTo
    {
        return $this->belongsTo(Reposteria::class);
    }

    public function ingrediente(): BelongsTo
    {
        return $this->belongsTo(Ingrediente::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    protected function casts(): array
    {
        return ['tipo' => MovimientoInventarioTipo::class, 'cantidad' => 'decimal:3', 'stock_anterior' => 'decimal:3', 'stock_nuevo' => 'decimal:3', 'fecha_movimiento' => 'datetime'];
    }
}
