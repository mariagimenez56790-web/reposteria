<?php

namespace App\Services;

use App\Enums\UnidadMedida;
use App\Models\Ingrediente;
use App\Models\Reposteria;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IngredienteService
{
    public function __construct(private InventarioAccessService $acceso) {}

    public function crear(User $actor, Reposteria $reposteria, array $datos): Ingrediente
    {
        $this->acceso->autorizarAdministracion($actor, $reposteria);
        $datos = $this->validar($datos, $reposteria);
        $ingrediente = new Ingrediente;
        $ingrediente->forceFill($datos + ['reposteria_id' => $reposteria->id, 'stock_actual' => '0.000', 'activo' => true])->save();

        return $ingrediente->refresh();
    }

    public function actualizar(User $actor, Ingrediente $ingrediente, array $datos): Ingrediente
    {
        $this->acceso->autorizarAdministracion($actor, $ingrediente->reposteria);
        $datos = $this->validar($datos, $ingrediente->reposteria, $ingrediente);
        if (isset($datos['unidad_medida']) && $datos['unidad_medida'] !== $ingrediente->unidad_medida->value && ($ingrediente->movimientos()->exists() || $ingrediente->recetas()->exists())) {
            throw new DomainException('No puede cambiarse la unidad de un ingrediente con historial o recetas.');
        }
        $ingrediente->forceFill($datos)->save();

        return $ingrediente->refresh();
    }

    public function establecerActivo(User $actor, Ingrediente $ingrediente, bool $activo): Ingrediente
    {
        $this->acceso->autorizarAdministracion($actor, $ingrediente->reposteria);
        $ingrediente->forceFill(['activo' => $activo])->save();

        return $ingrediente->refresh();
    }

    public function eliminar(User $actor, Ingrediente $ingrediente): void
    {
        $this->acceso->autorizarAdministracion($actor, $ingrediente->reposteria);
        if ($ingrediente->recetas()->where('recetas.activo', true)->exists() || $ingrediente->movimientos()->exists()) {
            throw new DomainException('Desactive el ingrediente: tiene receta activa o historial.');
        }
        $ingrediente->delete();
    }

    private function validar(array $datos, Reposteria $reposteria, ?Ingrediente $ingrediente = null): array
    {
        return Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:160', Rule::unique('ingredientes')->where('reposteria_id', $reposteria->id)->ignore($ingrediente)],
            'unidad_medida' => ['required', Rule::enum(UnidadMedida::class)],
            'stock_minimo' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999999.999'],
            'costo_unitario' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ])->validate();
    }
}
