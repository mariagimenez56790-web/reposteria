<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoriaService
{
    public function __construct(private CatalogoAccessService $acceso) {}

    public function crear(User $actor, Reposteria $reposteria, array $datos): Categoria
    {
        $this->acceso->autorizar($actor, $reposteria);
        $datos = $this->validar($datos, $reposteria);

        return $reposteria->categorias()->create($datos)->refresh();
    }

    public function actualizar(User $actor, Categoria $categoria, array $datos): Categoria
    {
        $this->acceso->autorizar($actor, $categoria->reposteria);
        $categoria->update($this->validar($datos, $categoria->reposteria, $categoria));

        return $categoria->refresh();
    }

    public function establecerActiva(User $actor, Categoria $categoria, bool $activa): Categoria
    {
        $this->acceso->autorizar($actor, $categoria->reposteria);
        $categoria->forceFill(['activo' => $activa])->save();

        return $categoria->refresh();
    }

    public function eliminar(User $actor, Categoria $categoria): void
    {
        $this->acceso->autorizar($actor, $categoria->reposteria);

        if ($categoria->productos()->exists()) {
            throw new DomainException('No se puede eliminar una categoría que tiene productos.');
        }

        $categoria->delete();
    }

    private function validar(array $datos, Reposteria $reposteria, ?Categoria $categoria = null): array
    {
        return Validator::make($datos, [
            'nombre' => [
                'required', 'string', 'max:120',
                Rule::unique('categorias')->where('reposteria_id', $reposteria->id)->ignore($categoria),
            ],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ])->validate();
    }
}
