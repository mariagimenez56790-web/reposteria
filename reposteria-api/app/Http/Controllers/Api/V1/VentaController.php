<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VentaEstado;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePedidoVentaRequest;
use App\Http\Requests\StoreVentaRequest;
use App\Http\Resources\VentaResource;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Reposteria;
use App\Models\Venta;
use App\Services\VentaAccessService;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, VentaAccessService $acceso): AnonymousResourceCollection
    {
        $acceso->autorizarOperacion($request->user(), $reposteria);
        $datos = $request->validate([
            'estado' => ['sometimes', Rule::enum(VentaEstado::class)],
            'cliente_id' => ['sometimes', 'integer'],
            'pedido_id' => ['sometimes', 'integer'],
            'fecha_desde' => ['sometimes', 'date'],
            'fecha_hasta' => ['sometimes', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if (isset($datos['cliente_id']) && ! Cliente::query()->whereKey($datos['cliente_id'])->where('reposteria_id', $reposteria->id)->exists()) {
            throw ValidationException::withMessages(['cliente_id' => 'El cliente no pertenece a la repostería.']);
        }
        if (isset($datos['pedido_id']) && ! Pedido::query()->whereKey($datos['pedido_id'])->where('reposteria_id', $reposteria->id)->exists()) {
            throw ValidationException::withMessages(['pedido_id' => 'El pedido no pertenece a la repostería.']);
        }

        $ventas = $reposteria->ventas()->with(['cliente', 'pedido'])
            ->when(isset($datos['estado']), fn ($query) => $query->where('estado', $datos['estado']))
            ->when(isset($datos['cliente_id']), fn ($query) => $query->where('cliente_id', $datos['cliente_id']))
            ->when(isset($datos['pedido_id']), fn ($query) => $query->where('pedido_id', $datos['pedido_id']))
            ->when(isset($datos['fecha_desde']), fn ($query) => $query->whereDate('fecha_venta', '>=', $datos['fecha_desde']))
            ->when(isset($datos['fecha_hasta']), fn ($query) => $query->whereDate('fecha_venta', '<=', $datos['fecha_hasta']))
            ->orderByDesc('fecha_venta')->orderByDesc('id')
            ->paginate($datos['per_page'] ?? 15);

        return VentaResource::collection($ventas);
    }

    public function show(Request $request, Reposteria $reposteria, int $venta, VentaAccessService $acceso): VentaResource
    {
        $acceso->autorizarOperacion($request->user(), $reposteria);

        return new VentaResource($this->venta($reposteria, $venta)->load(['cliente', 'pedido', 'detalles', 'pagos']));
    }

    public function store(StoreVentaRequest $request, Reposteria $reposteria, VentaService $ventas): JsonResponse
    {
        $datos = $request->validated();
        $detalles = $datos['detalles'];
        unset($datos['detalles']);
        $venta = $ventas->crearDirecta($request->user(), $reposteria, $datos, $detalles);

        return (new VentaResource($venta->load(['cliente', 'pedido', 'detalles', 'pagos'])))->response()->setStatusCode(201);
    }

    public function desdePedido(StorePedidoVentaRequest $request, Reposteria $reposteria, int $pedido, VentaService $ventas): JsonResponse
    {
        $modelo = Pedido::query()->whereKey($pedido)->where('reposteria_id', $reposteria->id)->firstOrFail();
        $venta = $ventas->crearDesdePedido($request->user(), $modelo, $request->validated());

        return (new VentaResource($venta->load(['cliente', 'pedido', 'detalles', 'pagos'])))->response()->setStatusCode(201);
    }

    public function anular(Request $request, Reposteria $reposteria, int $venta, VentaService $ventas): VentaResource
    {
        $modelo = $ventas->anular($request->user(), $this->venta($reposteria, $venta));

        return new VentaResource($modelo->load(['cliente', 'pedido', 'detalles', 'pagos']));
    }

    private function venta(Reposteria $reposteria, int $id): Venta
    {
        return Venta::query()->whereKey($id)->where('reposteria_id', $reposteria->id)->firstOrFail();
    }
}
