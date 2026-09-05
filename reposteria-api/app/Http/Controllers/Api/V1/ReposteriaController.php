<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReposteriaResource;
use App\Services\CatalogoConsultaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReposteriaController extends Controller
{
    public function index(Request $request, CatalogoConsultaService $catalogo): AnonymousResourceCollection
    {
        $datos = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        return ReposteriaResource::collection($catalogo->reposterias($request->user(), $datos['per_page'] ?? 15));
    }
}
