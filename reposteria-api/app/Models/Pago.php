<?php

namespace App\Models;

use App\Enums\MetodoPago;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['*'];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    protected function casts(): array
    {
        return [
            'metodo' => MetodoPago::class,
            'monto' => 'decimal:2',
            'fecha_pago' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
