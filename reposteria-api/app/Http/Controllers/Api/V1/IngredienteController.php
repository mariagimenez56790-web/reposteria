<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UnidadMedida;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIngredienteRequest;
use App\Http\Requests\UpdateIngredienteRequest;
use App\Http\Resources\IngredienteResource;
use App\Models\Ingrediente;
use App\Models\Reposteria;
use App\Services\IngredienteService;
use App\Services\InventarioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class IngredienteController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, InventarioAccessService $acceso): AnonymousResourceCollection
    {
        $acceso->autorizarConsulta($request->user(), $reposteria);
        $datos = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'unidad_medida' => ['sometimes', Rule::enum(UnidadMedida::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $ingredientes = $reposteria->ingredientes()
            ->when(isset($datos['search']), fn ($query) => $query->where('nombre', 'like', '%'.$datos['search'].'%'))
            ->when(isset($datos['activo']), fn ($query) => $query->where('activo', $datos['activo']))
            ->when(isset($datos['unidad_medida']), fn ($query) => $query->where('unidad_medida', $datos['unidad_medida']))
            ->orderBy('nombre')->paginate($datos['per_page'] ?? 15);

        return IngredienteResource::collection($ingredientes);
    }

    public function show(Request $request, Reposteria $reposteria, int $ingrediente, InventarioAccessService $acceso): IngredienteResource
    {
        $acceso->autorizarConsulta($request->user(), $reposteria);

        return new IngredienteResource($this->ingrediente($reposteria, $ingrediente));
    }

    public function store(StoreIngredienteRequest $request, Reposteria $reposteria, IngredienteService $ingredientes): JsonResponse
    {
        return (new IngredienteResource($ingredientes->crear($request->user(), $reposteria, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateIngredienteRequest $request, Reposteria $reposteria, int $ingrediente, IngredienteService $ingredientes): IngredienteResource
    {
        return new IngredienteResource($ingredientes->actualizar($request->user(), $this->ingrediente($reposteria, $ingrediente), $request->validated()));
    }

    private function ingrediente(Reposteria $reposteria, int $id): Ingrediente
    {
        return Ingrediente::query()->whereKey($id)->where('reposteria_id', $reposteria->id)->firstOrFail();
    }
}
