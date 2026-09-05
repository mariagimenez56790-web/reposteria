<?php

namespace App\Models;

use App\Enums\ReposteriaEstado;
use Database\Factories\ReposteriaFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Reposteria extends Model
{
    /** @use HasFactory<ReposteriaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Los campos administrativos, el propietario y el slug se asignan
     * exclusivamente mediante relaciones o lógica interna controlada.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'logo',
        'portada',
        'telefono',
        'email',
        'direccion',
        'ciudad',
    ];

    protected static function booted(): void
    {
        static::creating(function (Reposteria $reposteria): void {
            $propietario = User::query()->find($reposteria->propietario_id);

            if (! $propietario?->activo || ! $propietario->tieneRolInterno()) {
                throw new DomainException('El propietario debe ser un usuario interno activo.');
            }

            $reposteria->slug = static::slugUnico($reposteria->nombre);
            $reposteria->estado = ReposteriaEstado::Pendiente;
            $reposteria->aprobada_por = null;
            $reposteria->fecha_aprobacion = null;
            $reposteria->motivo_estado = null;
        });

        static::created(function (Reposteria $reposteria): void {
            $reposteria->usuarios()->syncWithoutDetaching([$reposteria->propietario_id]);
        });
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_id');
    }

    public function aprobadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    protected function casts(): array
    {
        return [
            'estado' => ReposteriaEstado::class,
            'fecha_aprobacion' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    private static function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'reposteria';
        $slug = $base;
        $sufijo = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }
}
