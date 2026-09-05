<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductoDetalleResource;
use App\Http\Resources\ProductoResource;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Reposteria;
use App\Services\CatalogoConsultaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, CatalogoConsultaService $catalogo): AnonymousResourceCollection
    {
        $catalogo->autorizar($request->user(), $reposteria);
        $datos = $request->validate(['categoria_id' => ['sometimes', 'integer'], 'search' => ['sometimes', 'string', 'max:100'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        if (isset($datos['categoria_id'])) {
            $categoria = Categoria::query()->find($datos['categoria_id']);
            if (! $categoria || $categoria->reposteria_id !== $reposteria->id) {
                throw ValidationException::withMessages(['categoria_id' => 'La categoría no pertenece a la repostería.']);
            }
        }

        $productos = $reposteria->productos()->where('activo', true)
            ->when(isset($datos['categoria_id']), fn ($query) => $query->where('categoria_id', $datos['categoria_id']))
            ->when(isset($datos['search']), fn ($query) => $query->where('nombre', 'like', '%'.$datos['search'].'%'))
            ->with(['promociones' => fn ($query) => $query->vigentes()])
            ->withCount(['variantes' => fn ($query) => $query->where('activo', true)])
            ->orderBy('nombre')->paginate($datos['per_page'] ?? 15);

        return ProductoResource::collection($productos);
    }

    public function show(Request $request, Reposteria $reposteria, int $producto, CatalogoConsultaService $catalogo): ProductoDetalleResource
    {
        $catalogo->autorizar($request->user(), $reposteria);
        $modelo = Producto::query()->whereKey($producto)->where('reposteria_id', $reposteria->id)->where('activo', true)
            ->with(['promociones' => fn ($query) => $query->vigentes(), 'variantes' => fn ($query) => $query->where('activo', true)->orderBy('nombre')->with(['promociones' => fn ($promociones) => $promociones->vigentes()])])
            ->withCount(['variantes' => fn ($query) => $query->where('activo', true)])->firstOrFail();
        $modelo->variantes->each(fn ($variante) => $variante->setRelation('producto', $modelo));

        return new ProductoDetalleResource($modelo);
    }
}
