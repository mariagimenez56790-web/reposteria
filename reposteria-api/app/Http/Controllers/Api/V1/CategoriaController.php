<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Models\Reposteria;
use App\Services\CatalogoConsultaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoriaController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, CatalogoConsultaService $catalogo): AnonymousResourceCollection
    {
        $catalogo->autorizar($request->user(), $reposteria);

        return CategoriaResource::collection($reposteria->categorias()->where('activo', true)->orderBy('nombre')->get());
    }
}
