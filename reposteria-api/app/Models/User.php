<?php

namespace App\Models;

use App\Enums\ReposteriaEstado;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function reposteriasComoPropietario(): HasMany
    {
        return $this->hasMany(Reposteria::class, 'propietario_id');
    }

    public function reposteriasAprobadas(): HasMany
    {
        return $this->hasMany(Reposteria::class, 'aprobada_por');
    }

    public function movimientosInventarioCreados(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'creado_por');
    }

    public function reposterias(): BelongsToMany
    {
        return $this->belongsToMany(Reposteria::class)->withTimestamps();
    }

    public function esSuperadmin(): bool
    {
        return $this->activo && $this->role()->where('nombre', 'superadmin')->exists();
    }

    public function tieneRolInterno(): bool
    {
        return in_array($this->role?->nombre, ['admin', 'vendedor', 'produccion'], true);
    }

    public function perteneceAReposteria(Reposteria $reposteria): bool
    {
        return $this->reposterias()->whereKey($reposteria->id)->exists();
    }

    public function puedeOperarEnReposteria(Reposteria $reposteria): bool
    {
        return $this->activo
            && $this->tieneRolInterno()
            && $reposteria->estado === ReposteriaEstado::Aprobada
            && ! $reposteria->trashed()
            && $this->perteneceAReposteria($reposteria);
    }

    public function puedeAccederAReposteria(Reposteria $reposteria): bool
    {
        return $this->esSuperadmin() || $this->puedeOperarEnReposteria($reposteria);
    }
}
