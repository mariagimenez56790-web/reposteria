<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\ProductoController;
use App\Http\Controllers\Api\V1\ReposteriaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('v1')->group(function () {
        Route::get('/reposterias', [ReposteriaController::class, 'index']);
        Route::get('/reposterias/{reposteria}/categorias', [CategoriaController::class, 'index']);
        Route::get('/reposterias/{reposteria}/productos', [ProductoController::class, 'index']);
        Route::get('/reposterias/{reposteria}/productos/{producto}', [ProductoController::class, 'show']);
    });
});
