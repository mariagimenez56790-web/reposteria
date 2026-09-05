<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use App\Models\Reposteria;
use App\Services\ClienteAccessService;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClienteController extends Controller
{
    public function index(Request $request, Reposteria $reposteria, ClienteAccessService $acceso): AnonymousResourceCollection
    {
        $acceso->autorizarLecturaEscritura($request->user(), $reposteria);
        $datos = $request->validate(['search' => ['sometimes', 'string', 'max:100'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $clientes = $reposteria->clientes()->when(isset($datos['search']), function ($query) use ($datos) {
            $busqueda = '%'.$datos['search'].'%';
            $query->where(fn ($campos) => $campos->where('nombre', 'like', $busqueda)->orWhere('telefono', 'like', $busqueda)->orWhere('email', 'like', $busqueda));
        })->orderBy('nombre')->paginate($datos['per_page'] ?? 15);

        return ClienteResource::collection($clientes);
    }

    public function show(Request $request, Reposteria $reposteria, int $cliente, ClienteAccessService $acceso): ClienteResource
    {
        $acceso->autorizarLecturaEscritura($request->user(), $reposteria);

        return new ClienteResource(Cliente::query()->whereKey($cliente)->where('reposteria_id', $reposteria->id)->firstOrFail());
    }

    public function store(StoreClienteRequest $request, Reposteria $reposteria, ClienteService $clientes): JsonResponse
    {
        return (new ClienteResource($clientes->crear($request->user(), $reposteria, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateClienteRequest $request, Reposteria $reposteria, int $cliente, ClienteService $clientes): ClienteResource
    {
        $modelo = Cliente::query()->whereKey($cliente)->where('reposteria_id', $reposteria->id)->firstOrFail();

        return new ClienteResource($clientes->actualizar($request->user(), $modelo, $request->validated()));
    }
}
