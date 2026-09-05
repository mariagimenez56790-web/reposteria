<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmpleadoRequest;
use App\Http\Requests\UpdateEmpleadoRequest;
use App\Http\Resources\EmpleadoResource;
use App\Models\Reposteria;
use App\Models\User;
use App\Services\ReposteriaUsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class EmpleadoController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, ReposteriaUsuarioService $usuarios): AnonymousResourceCollection
    {
        $usuarios->autorizarGestion($request->user(), $reposteria);
        $datos = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'rol' => ['sometimes', Rule::in(['admin', 'vendedor', 'produccion'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $empleados = $reposteria->usuarios()->with('role')
            ->when(isset($datos['search']), function ($query) use ($datos) {
                $busqueda = '%'.$datos['search'].'%';
                $query->where(fn ($campos) => $campos->where('name', 'like', $busqueda)->orWhere('email', 'like', $busqueda));
            })
            ->when(isset($datos['activo']), fn ($query) => $query->where('activo', $datos['activo']))
            ->when(isset($datos['rol']), fn ($query) => $query->whereHas('role', fn ($roles) => $roles->where('nombre', $datos['rol'])))
            ->orderBy('name')->paginate($datos['per_page'] ?? 15);
        $empleados->getCollection()->each(fn (User $usuario) => $usuario->setAttribute('reposteria_consultada_id', $reposteria->propietario_id));

        return EmpleadoResource::collection($empleados);
    }

    public function show(Request $request, Reposteria $reposteria, int $usuario, ReposteriaUsuarioService $usuarios): EmpleadoResource
    {
        $usuarios->autorizarGestion($request->user(), $reposteria);

        return new EmpleadoResource($this->usuario($reposteria, $usuario)->setAttribute('reposteria_consultada_id', $reposteria->propietario_id));
    }

    public function store(StoreEmpleadoRequest $request, Reposteria $reposteria, ReposteriaUsuarioService $usuarios): JsonResponse
    {
        $usuario = $usuarios->crear($request->user(), $reposteria, $request->validated());
        $usuario = $this->usuario($reposteria, $usuario->id)->setAttribute('reposteria_consultada_id', $reposteria->propietario_id);

        return (new EmpleadoResource($usuario))->response()->setStatusCode(201);
    }

    public function update(UpdateEmpleadoRequest $request, Reposteria $reposteria, int $usuario, ReposteriaUsuarioService $usuarios): EmpleadoResource
    {
        $modelo = $usuarios->actualizar($request->user(), $reposteria, $this->usuario($reposteria, $usuario), $request->validated());
        $modelo = $this->usuario($reposteria, $modelo->id)->setAttribute('reposteria_consultada_id', $reposteria->propietario_id);

        return new EmpleadoResource($modelo);
    }

    public function destroy(Request $request, Reposteria $reposteria, int $usuario, ReposteriaUsuarioService $usuarios): Response
    {
        $usuarios->retirar($request->user(), $reposteria, $this->usuario($reposteria, $usuario));

        return response()->noContent();
    }

    private function usuario(Reposteria $reposteria, int $id): User
    {
        return $reposteria->usuarios()->with('role')->whereKey($id)->firstOrFail();
    }
}
