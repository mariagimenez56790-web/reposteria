<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PedidoEstado;
use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarPedidoEstadoRequest;
use App\Http\Requests\StorePedidoRequest;
use App\Http\Requests\UpdatePedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Reposteria;
use App\Services\CatalogoConsultaService;
use App\Services\PedidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, CatalogoConsultaService $acceso): AnonymousResourceCollection
    {
        $acceso->autorizar($request->user(), $reposteria);
        $datos = $request->validate(['estado' => ['sometimes', Rule::enum(PedidoEstado::class)], 'cliente_id' => ['sometimes', 'integer'], 'fecha_desde' => ['sometimes', 'date'], 'fecha_hasta' => ['sometimes', 'date', 'after_or_equal:fecha_desde'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        if (isset($datos['cliente_id']) && ! Cliente::query()->whereKey($datos['cliente_id'])->where('reposteria_id', $reposteria->id)->exists()) {
            throw ValidationException::withMessages(['cliente_id' => 'El cliente no pertenece a la repostería.']);
        }
        $pedidos = $reposteria->pedidos()->with('cliente')
            ->when(isset($datos['estado']), fn ($query) => $query->where('estado', $datos['estado']))
            ->when(isset($datos['cliente_id']), fn ($query) => $query->where('cliente_id', $datos['cliente_id']))
            ->when(isset($datos['fecha_desde']), fn ($query) => $query->whereDate('fecha_pedido', '>=', $datos['fecha_desde']))
            ->when(isset($datos['fecha_hasta']), fn ($query) => $query->whereDate('fecha_pedido', '<=', $datos['fecha_hasta']))
            ->orderByDesc('fecha_pedido')->orderByDesc('id')->paginate($datos['per_page'] ?? 15);

        return PedidoResource::collection($pedidos);
    }

    public function show(Request $request, Reposteria $reposteria, int $pedido, CatalogoConsultaService $acceso): PedidoResource
    {
        $acceso->autorizar($request->user(), $reposteria);

        return new PedidoResource($this->pedido($reposteria, $pedido)->load(['cliente', 'detalles']));
    }

    public function store(StorePedidoRequest $request, Reposteria $reposteria, PedidoService $pedidos): JsonResponse
    {
        $datos = $request->validated();
        $detalles = $datos['detalles'];
        unset($datos['detalles']);
        $pedido = $pedidos->crear($request->user(), $reposteria, $datos, $detalles);

        return (new PedidoResource($pedido->load(['cliente', 'detalles'])))->response()->setStatusCode(201);
    }

    public function update(UpdatePedidoRequest $request, Reposteria $reposteria, int $pedido, PedidoService $pedidos): PedidoResource
    {
        $modelo = $pedidos->actualizar($request->user(), $this->pedido($reposteria, $pedido), $request->validated());

        return new PedidoResource($modelo->load(['cliente', 'detalles']));
    }

    public function cambiarEstado(CambiarPedidoEstadoRequest $request, Reposteria $reposteria, int $pedido, PedidoService $pedidos): PedidoResource
    {
        $modelo = $pedidos->cambiarEstado($request->user(), $this->pedido($reposteria, $pedido), PedidoEstado::from($request->validated('estado')));

        return new PedidoResource($modelo->load(['cliente', 'detalles']));
    }

    private function pedido(Reposteria $reposteria, int $id): Pedido
    {
        return Pedido::query()->whereKey($id)->where('reposteria_id', $reposteria->id)->firstOrFail();
    }
}
