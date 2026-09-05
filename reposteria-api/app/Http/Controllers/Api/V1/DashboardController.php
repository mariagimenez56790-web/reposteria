<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reposteria;
use App\Services\DashboardService;
use App\Services\ReporteAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Reposteria $reposteria, ReporteAccessService $acceso, DashboardService $dashboard): JsonResponse
    {
        $acceso->comercial($request->user(), $reposteria);

        return response()->json($dashboard->obtener($reposteria));
    }
}
