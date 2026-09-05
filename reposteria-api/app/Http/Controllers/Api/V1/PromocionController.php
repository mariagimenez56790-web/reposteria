<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PromocionTipoDescuento;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromocionRequest;
use App\Http\Requests\UpdatePromocionRequest;
use App\Http\Resources\PromocionResource;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Promocion;
use App\Models\Reposteria;
use App\Services\PromocionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PromocionController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, PromocionService $promociones): AnonymousResourceCollection
    {
        $promociones->autorizarAdministracion($request->user(), $reposteria);
        $datos = $request->validate([
            'activo' => ['sometimes', 'boolean'],
            'tipo_descuento' => ['sometimes', Rule::enum(PromocionTipoDescuento::class)],
            'vigente' => ['sometimes', 'boolean'],
            'producto_id' => ['sometimes', 'integer'],
            'variante_id' => ['sometimes', 'integer'],
            'fecha_desde' => ['sometimes', 'date'],
            'fecha_hasta' => ['sometimes', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if (isset($datos['activo'])) {
            $datos['activo'] = $request->boolean('activo');
        }
        if (isset($datos['vigente'])) {
            $datos['vigente'] = $request->boolean('vigente');
        }
        $this->validarFiltros($reposteria, $datos);
        $ahora = now();
        $listado = $reposteria->promociones()->with(['productos', 'variantes.producto'])
            ->when(isset($datos['activo']), fn ($query) => $query->where('activo', $datos['activo']))
            ->when(isset($datos['tipo_descuento']), fn ($query) => $query->where('tipo_descuento', $datos['tipo_descuento']))
            ->when(($datos['vigente'] ?? null) === true, fn ($query) => $query->vigentes($ahora))
            ->when(($datos['vigente'] ?? null) === false, fn ($query) => $query->where(fn ($estado) => $estado->where('activo', false)->orWhere('fecha_inicio', '>', $ahora)->orWhere('fecha_fin', '<', $ahora)))
            ->when(isset($datos['producto_id']), fn ($query) => $query->whereHas('productos', fn ($productos) => $productos->whereKey($datos['producto_id'])))
            ->when(isset($datos['variante_id']), fn ($query) => $query->whereHas('variantes', fn ($variantes) => $variantes->whereKey($datos['variante_id'])))
            ->when(isset($datos['fecha_desde']), fn ($query) => $query->where('fecha_inicio', '>=', $datos['fecha_desde']))
            ->when(isset($datos['fecha_hasta']), fn ($query) => $query->where('fecha_fin', '<=', $datos['fecha_hasta']))
            ->orderByDesc('fecha_inicio')->orderByDesc('id')->paginate($datos['per_page'] ?? 15);

        return PromocionResource::collection($listado);
    }

    public function show(Request $request, Reposteria $reposteria, int $promocion, PromocionService $promociones): PromocionResource
    {
        $promociones->autorizarAdministracion($request->user(), $reposteria);

        return new PromocionResource($this->promocion($reposteria, $promocion)->load(['productos', 'variantes.producto']));
    }

    public function store(StorePromocionRequest $request, Reposteria $reposteria, PromocionService $promociones): JsonResponse
    {
        $datos = $request->validated();
        $productoIds = $datos['producto_ids'] ?? [];
        $varianteIds = $datos['variante_ids'] ?? [];
        unset($datos['producto_ids'], $datos['variante_ids']);
        $promocion = $promociones->crearCompleta($request->user(), $reposteria, $datos, $productoIds, $varianteIds);

        return (new PromocionResource($promocion))->response()->setStatusCode(201);
    }

    public function update(UpdatePromocionRequest $request, Reposteria $reposteria, int $promocion, PromocionService $promociones): PromocionResource
    {
        $datos = $request->validated();
        $productoIds = array_key_exists('producto_ids', $datos) ? $datos['producto_ids'] : null;
        $varianteIds = array_key_exists('variante_ids', $datos) ? $datos['variante_ids'] : null;
        $activa = array_key_exists('activo', $datos) ? $datos['activo'] : null;
        unset($datos['producto_ids'], $datos['variante_ids'], $datos['activo']);

        return new PromocionResource($promociones->actualizarCompleta($request->user(), $this->promocion($reposteria, $promocion), $datos, $productoIds, $varianteIds, $activa));
    }

    public function destroy(Request $request, Reposteria $reposteria, int $promocion, PromocionService $promociones): Response
    {
        $promociones->eliminar($request->user(), $this->promocion($reposteria, $promocion));

        return response()->noContent();
    }

    private function promocion(Reposteria $reposteria, int $id): Promocion
    {
        return Promocion::query()->whereKey($id)->where('reposteria_id', $reposteria->id)->firstOrFail();
    }

    private function validarFiltros(Reposteria $reposteria, array $datos): void
    {
        if (isset($datos['producto_id']) && ! Producto::query()->whereKey($datos['producto_id'])->where('reposteria_id', $reposteria->id)->exists()) {
            throw ValidationException::withMessages(['producto_id' => 'El producto no pertenece a la repostería.']);
        }
        if (isset($datos['variante_id']) && ! ProductoVariante::query()->whereKey($datos['variante_id'])->whereHas('producto', fn ($query) => $query->where('reposteria_id', $reposteria->id))->exists()) {
            throw ValidationException::withMessages(['variante_id' => 'La variante no pertenece a la repostería.']);
        }
    }
}
