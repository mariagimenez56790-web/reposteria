<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReposteriaEstado;
use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarEstadoReposteriaRequest;
use App\Http\Resources\AdminReposteriaResource;
use App\Models\Reposteria;
use App\Services\ReposteriaEstadoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminReposteriaController extends Controller
{
    public function index(Request $request, ReposteriaEstadoService $estados): AnonymousResourceCollection
    {
        $estados->autorizar($request->user());
        $datos = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'estado' => ['sometimes', Rule::enum(ReposteriaEstado::class)],
            'propietario_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $reposterias = Reposteria::query()->with(['propietario', 'aprobadaPor'])
            ->when(isset($datos['search']), function ($query) use ($datos) {
                $busqueda = '%'.$datos['search'].'%';
                $query->where(fn ($campos) => $campos->where('nombre', 'like', $busqueda)->orWhere('slug', 'like', $busqueda)->orWhereHas('propietario', fn ($propietarios) => $propietarios->where('name', 'like', $busqueda)->orWhere('email', 'like', $busqueda)));
            })
            ->when(isset($datos['estado']), fn ($query) => $query->where('estado', $datos['estado']))
            ->when(isset($datos['propietario_id']), fn ($query) => $query->where('propietario_id', $datos['propietario_id']))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($datos['per_page'] ?? 15);

        return AdminReposteriaResource::collection($reposterias);
    }

    public function show(Request $request, Reposteria $reposteria, ReposteriaEstadoService $estados): AdminReposteriaResource
    {
        $estados->autorizar($request->user());

        return $this->resource($reposteria);
    }

    public function aprobar(CambiarEstadoReposteriaRequest $request, Reposteria $reposteria, ReposteriaEstadoService $estados): AdminReposteriaResource
    {
        return $this->resource($estados->aprobar($reposteria, $request->user()));
    }

    public function rechazar(CambiarEstadoReposteriaRequest $request, Reposteria $reposteria, ReposteriaEstadoService $estados): AdminReposteriaResource
    {
        return $this->resource($estados->rechazar($reposteria, $request->user(), $request->validated('motivo')));
    }

    public function suspender(CambiarEstadoReposteriaRequest $request, Reposteria $reposteria, ReposteriaEstadoService $estados): AdminReposteriaResource
    {
        return $this->resource($estados->suspender($reposteria, $request->user(), $request->validated('motivo')));
    }

    public function inactivar(CambiarEstadoReposteriaRequest $request, Reposteria $reposteria, ReposteriaEstadoService $estados): AdminReposteriaResource
    {
        return $this->resource($estados->inactivar($reposteria, $request->user(), $request->validated('motivo')));
    }

    private function resource(Reposteria $reposteria): AdminReposteriaResource
    {
        return new AdminReposteriaResource($reposteria->load(['propietario', 'aprobadaPor']));
    }
}
