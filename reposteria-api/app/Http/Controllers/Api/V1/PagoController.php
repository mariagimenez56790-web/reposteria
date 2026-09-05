<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePagoRequest;
use App\Http\Resources\PagoResource;
use App\Models\Pago;
use App\Models\Reposteria;
use App\Models\Venta;
use App\Services\PagoService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PagoController extends Controller
{
    public function store(StorePagoRequest $request, Reposteria $reposteria, int $venta, PagoService $pagos): JsonResponse
    {
        $pago = $pagos->registrar($request->user(), $this->venta($reposteria, $venta), $request->validated());

        return (new PagoResource($pago))->response()->setStatusCode(201);
    }

    public function destroy(Reposteria $reposteria, int $venta, int $pago, PagoService $pagos): Response
    {
        $pagos->anular(request()->user(), $this->pago($reposteria, $venta, $pago));

        return response()->noContent();
    }

    private function venta(Reposteria $reposteria, int $id): Venta
    {
        return Venta::query()->whereKey($id)->where('reposteria_id', $reposteria->id)->firstOrFail();
    }

    private function pago(Reposteria $reposteria, int $venta, int $pago): Pago
    {
        return Pago::query()->whereKey($pago)->where('venta_id', $this->venta($reposteria, $venta)->id)->firstOrFail();
    }
}
