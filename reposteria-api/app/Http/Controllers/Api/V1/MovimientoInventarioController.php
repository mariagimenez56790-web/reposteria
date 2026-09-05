<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MovimientoInventarioTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMovimientoInventarioRequest;
use App\Http\Resources\MovimientoInventarioResource;
use App\Models\Ingrediente;
use App\Models\Reposteria;
use App\Services\InventarioAccessService;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovimientoInventarioController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, InventarioAccessService $acceso): AnonymousResourceCollection
    {
        $acceso->autorizarConsulta($request->user(), $reposteria);
        $datos = $request->validate([
            'ingrediente_id' => ['sometimes', 'integer'],
            'tipo' => ['sometimes', Rule::enum(MovimientoInventarioTipo::class)],
            'fecha_desde' => ['sometimes', 'date'],
            'fecha_hasta' => ['sometimes', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if (isset($datos['ingrediente_id']) && ! Ingrediente::query()->whereKey($datos['ingrediente_id'])->where('reposteria_id', $reposteria->id)->exists()) {
            throw ValidationException::withMessages(['ingrediente_id' => 'El ingrediente no pertenece a la repostería.']);
        }
        $movimientos = $reposteria->movimientosInventario()->with(['ingrediente', 'creadoPor'])
            ->when(isset($datos['ingrediente_id']), fn ($query) => $query->where('ingrediente_id', $datos['ingrediente_id']))
            ->when(isset($datos['tipo']), fn ($query) => $query->where('tipo', $datos['tipo']))
            ->when(isset($datos['fecha_desde']), fn ($query) => $query->whereDate('fecha_movimiento', '>=', $datos['fecha_desde']))
            ->when(isset($datos['fecha_hasta']), fn ($query) => $query->whereDate('fecha_movimiento', '<=', $datos['fecha_hasta']))
            ->orderByDesc('fecha_movimiento')->orderByDesc('id')->paginate($datos['per_page'] ?? 15);

        return MovimientoInventarioResource::collection($movimientos);
    }

    public function store(StoreMovimientoInventarioRequest $request, Reposteria $reposteria, InventarioService $inventario): JsonResponse
    {
        $datos = $request->validated();
        $ingrediente = Ingrediente::query()->whereKey($datos['ingrediente_id'])->where('reposteria_id', $reposteria->id)->firstOrFail();
        $tipo = MovimientoInventarioTipo::from($datos['tipo']);
        unset($datos['ingrediente_id'], $datos['tipo']);
        $movimiento = match ($tipo) {
            MovimientoInventarioTipo::Entrada => $inventario->entrada($request->user(), $ingrediente, $datos),
            MovimientoInventarioTipo::Salida => $inventario->salida($request->user(), $ingrediente, $datos),
            MovimientoInventarioTipo::AjustePositivo => $inventario->ajustePositivo($request->user(), $ingrediente, $datos),
            MovimientoInventarioTipo::AjusteNegativo => $inventario->ajusteNegativo($request->user(), $ingrediente, $datos),
        };

        return (new MovimientoInventarioResource($movimiento->load(['ingrediente', 'creadoPor'])))->response()->setStatusCode(201);
    }
}
