<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecetaRequest;
use App\Http\Requests\UpdateRecetaRequest;
use App\Http\Resources\RecetaResource;
use App\Models\Receta;
use App\Models\Reposteria;
use App\Services\InventarioAccessService;
use App\Services\RecetaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecetaController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, InventarioAccessService $acceso): AnonymousResourceCollection
    {
        $acceso->autorizarConsulta($request->user(), $reposteria);
        $datos = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'producto_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $recetas = $reposteria->recetas()->with(['producto', 'ingredientes'])
            ->when(isset($datos['search']), fn ($query) => $query->where('nombre', 'like', '%'.$datos['search'].'%'))
            ->when(isset($datos['activo']), fn ($query) => $query->where('activo', $datos['activo']))
            ->when(isset($datos['producto_id']), fn ($query) => $query->where('producto_id', $datos['producto_id']))
            ->orderBy('nombre')->paginate($datos['per_page'] ?? 15);

        return RecetaResource::collection($recetas);
    }

    public function show(Request $request, Reposteria $reposteria, int $receta, InventarioAccessService $acceso): RecetaResource
    {
        $acceso->autorizarConsulta($request->user(), $reposteria);

        return new RecetaResource($this->receta($reposteria, $receta)->load(['producto', 'ingredientes']));
    }

    public function store(StoreRecetaRequest $request, Reposteria $reposteria, RecetaService $recetas): JsonResponse
    {
        $datos = $request->validated();
        $ingredientes = $datos['ingredientes'] ?? [];
        unset($datos['ingredientes']);
        $receta = $recetas->crearCompleta($request->user(), $reposteria, $datos, $ingredientes);

        return (new RecetaResource($receta))->response()->setStatusCode(201);
    }

    public function update(UpdateRecetaRequest $request, Reposteria $reposteria, int $receta, RecetaService $recetas): RecetaResource
    {
        $datos = $request->validated();
        $ingredientes = array_key_exists('ingredientes', $datos) ? $datos['ingredientes'] : null;
        unset($datos['ingredientes']);

        return new RecetaResource($recetas->actualizarCompleta($request->user(), $this->receta($reposteria, $receta), $datos, $ingredientes));
    }

    private function receta(Reposteria $reposteria, int $id): Receta
    {
        return Receta::query()->whereKey($id)->where('reposteria_id', $reposteria->id)->firstOrFail();
    }
}
