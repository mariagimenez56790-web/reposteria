<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->activo) {
            return new JsonResponse(['message' => 'La cuenta no está disponible.'], 403);
        }

        return $next($request);
    }
}
