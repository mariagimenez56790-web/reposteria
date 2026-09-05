<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $datos = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'min:1', 'max:100'],
        ])->validate();

        $usuario = User::query()->where('email', $datos['email'])->first();
        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }
        if (! $usuario->activo) {
            return response()->json(['message' => 'La cuenta no está disponible.'], 403);
        }

        $token = $usuario->createToken($datos['device_name'] ?? 'flutter')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'data' => ['token' => $token, 'token_type' => 'Bearer', 'user' => (new UserResource($usuario))->resolve($request)],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => (new UserResource($request->user()))->resolve($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
