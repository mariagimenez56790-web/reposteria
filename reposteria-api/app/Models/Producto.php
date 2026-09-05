<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre', 'descripcion', 'precio', 'imagen', 'personalizable', 'maneja_stock', 'stock',
    ];

    public function reposteria(): BelongsTo
    {
        return $this->belongsTo(Reposteria::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(ProductoVariante::class);
    }

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'personalizable' => 'boolean',
            'maneja_stock' => 'boolean',
            'stock' => 'integer',
            'activo' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
