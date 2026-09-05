<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PedidoEstado;
use App\Enums\UnidadMedida;
use App\Enums\VentaEstado;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Reposteria;
use App\Services\ReporteAccessService;
use App\Services\ReporteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReporteController extends Controller
{
    public function ventas(Request $request, Reposteria $reposteria, ReporteAccessService $acceso, ReporteService $reportes): JsonResponse
    {
        $acceso->comercial($request->user(), $reposteria);
        $datos = $request->validate($this->reglasComunes(Rule::enum(VentaEstado::class)));
        $this->validar($datos, $reposteria);

        return response()->json($reportes->ventas($reposteria, $datos));
    }

    public function pedidos(Request $request, Reposteria $reposteria, ReporteAccessService $acceso, ReporteService $reportes): JsonResponse
    {
        $acceso->pedidos($request->user(), $reposteria);
        $datos = $request->validate($this->reglasComunes(Rule::enum(PedidoEstado::class)));
        $this->validar($datos, $reposteria);

        return response()->json($reportes->pedidos($reposteria, $datos));
    }

    public function inventario(Request $request, Reposteria $reposteria, ReporteAccessService $acceso, ReporteService $reportes): JsonResponse
    {
        $acceso->inventario($request->user(), $reposteria);
        $datos = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'unidad' => ['sometimes', Rule::enum(UnidadMedida::class)],
            'stock_bajo' => ['sometimes', 'boolean'],
            'sin_stock' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($reportes->inventario($reposteria, $datos));
    }

    private function reglasComunes($estado): array
    {
        return [
            'fecha_desde' => ['sometimes', 'date_format:Y-m-d'],
            'fecha_hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'estado' => ['sometimes', $estado],
            'cliente_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function validar(array $datos, Reposteria $reposteria): void
    {
        if (isset($datos['cliente_id']) && ! Cliente::query()->whereKey($datos['cliente_id'])->where('reposteria_id', $reposteria->id)->exists()) {
            throw ValidationException::withMessages(['cliente_id' => 'El cliente no pertenece a la repostería.']);
        }
        if (isset($datos['fecha_desde'], $datos['fecha_hasta']) && CarbonImmutable::parse($datos['fecha_desde'])->diffInDays(CarbonImmutable::parse($datos['fecha_hasta'])) > 366) {
            throw ValidationException::withMessages(['fecha_hasta' => 'El rango máximo permitido es de 366 días.']);
        }
    }
}
