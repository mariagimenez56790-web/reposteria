<?php

namespace App\Models;

use Database\Factories\RecetaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receta extends Model
{
    /** @use HasFactory<RecetaFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['reposteria_id', 'producto_id'];

    public function reposteria(): BelongsTo
    {
        return $this->belongsTo(Reposteria::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ingredientes(): BelongsToMany
    {
        return $this->belongsToMany(Ingrediente::class)->withPivot('cantidad')->withTimestamps();
    }

    protected function casts(): array
    {
        return ['rendimiento' => 'decimal:3', 'activo' => 'boolean', 'deleted_at' => 'datetime'];
    }
}
