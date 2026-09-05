<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReposteriaPerfilRequest;
use App\Http\Resources\ReposteriaPerfilResource;
use App\Models\Reposteria;
use App\Services\ReposteriaPerfilService;
use Illuminate\Http\Request;

class ReposteriaPerfilController extends Controller
{
    public function show(Request $request, Reposteria $reposteria, ReposteriaPerfilService $perfiles): ReposteriaPerfilResource
    {
        $perfiles->autorizar($request->user(), $reposteria);

        return new ReposteriaPerfilResource($reposteria);
    }

    public function update(UpdateReposteriaPerfilRequest $request, Reposteria $reposteria, ReposteriaPerfilService $perfiles): ReposteriaPerfilResource
    {
        return new ReposteriaPerfilResource($perfiles->actualizar(
            $request->user(),
            $reposteria,
            $request->safe()->only(['nombre', 'descripcion', 'telefono', 'email', 'direccion', 'ciudad']),
        ));
    }
}
