<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePedidoDetalleRequest;
use App\Http\Requests\UpdatePedidoDetalleRequest;
use App\Http\Resources\PedidoDetalleResource;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Reposteria;
use App\Services\PedidoService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PedidoDetalleController extends Controller
{
    public function store(StorePedidoDetalleRequest $request, Reposteria $reposteria, int $pedido, PedidoService $pedidos): JsonResponse
    {
        return (new PedidoDetalleResource($pedidos->agregarDetalle($request->user(), $this->pedido($reposteria, $pedido), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdatePedidoDetalleRequest $request, Reposteria $reposteria, int $pedido, int $detalle, PedidoService $pedidos): PedidoDetalleResource
    {
        $modelo = $this->detalle($reposteria, $pedido, $detalle);
        $datos = ['producto_id' => $modelo->producto_id, 'producto_variante_id' => $modelo->producto_variante_id, 'cantidad' => $request->validated('cantidad')];

        return new PedidoDetalleResource($pedidos->modificarDetalle($request->user(), $modelo, $datos));
    }

    public function destroy(Reposteria $reposteria, int $pedido, int $detalle, PedidoService $pedidos): Response
    {
        $pedidos->eliminarDetalle(request()->user(), $this->detalle($reposteria, $pedido, $detalle));

        return response()->noContent();
    }

    private function pedido(Reposteria $reposteria, int $id): Pedido
    {
        return Pedido::query()->whereKey($id)->where('reposteria_id', $reposteria->id)->firstOrFail();
    }

    private function detalle(Reposteria $reposteria, int $pedido, int $detalle): PedidoDetalle
    {
        return PedidoDetalle::query()->whereKey($detalle)->where('pedido_id', $this->pedido($reposteria, $pedido)->id)->firstOrFail();
    }
}
