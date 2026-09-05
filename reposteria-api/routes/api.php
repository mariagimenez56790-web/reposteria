<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\ClienteController;
use App\Http\Controllers\Api\V1\IngredienteController;
use App\Http\Controllers\Api\V1\MovimientoInventarioController;
use App\Http\Controllers\Api\V1\PagoController;
use App\Http\Controllers\Api\V1\PedidoController;
use App\Http\Controllers\Api\V1\PedidoDetalleController;
use App\Http\Controllers\Api\V1\ProductoController;
use App\Http\Controllers\Api\V1\RecetaController;
use App\Http\Controllers\Api\V1\ReposteriaController;
use App\Http\Controllers\Api\V1\VentaController;
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
        Route::get('/reposterias/{reposteria}/clientes', [ClienteController::class, 'index']);
        Route::get('/reposterias/{reposteria}/clientes/{cliente}', [ClienteController::class, 'show']);
        Route::post('/reposterias/{reposteria}/clientes', [ClienteController::class, 'store']);
        Route::patch('/reposterias/{reposteria}/clientes/{cliente}', [ClienteController::class, 'update']);
        Route::get('/reposterias/{reposteria}/pedidos', [PedidoController::class, 'index']);
        Route::get('/reposterias/{reposteria}/pedidos/{pedido}', [PedidoController::class, 'show']);
        Route::post('/reposterias/{reposteria}/pedidos', [PedidoController::class, 'store']);
        Route::patch('/reposterias/{reposteria}/pedidos/{pedido}', [PedidoController::class, 'update']);
        Route::post('/reposterias/{reposteria}/pedidos/{pedido}/estado', [PedidoController::class, 'cambiarEstado']);
        Route::post('/reposterias/{reposteria}/pedidos/{pedido}/detalles', [PedidoDetalleController::class, 'store']);
        Route::patch('/reposterias/{reposteria}/pedidos/{pedido}/detalles/{detalle}', [PedidoDetalleController::class, 'update']);
        Route::delete('/reposterias/{reposteria}/pedidos/{pedido}/detalles/{detalle}', [PedidoDetalleController::class, 'destroy']);
        Route::post('/reposterias/{reposteria}/pedidos/{pedido}/venta', [VentaController::class, 'desdePedido']);
        Route::get('/reposterias/{reposteria}/ventas', [VentaController::class, 'index']);
        Route::get('/reposterias/{reposteria}/ventas/{venta}', [VentaController::class, 'show']);
        Route::post('/reposterias/{reposteria}/ventas', [VentaController::class, 'store']);
        Route::post('/reposterias/{reposteria}/ventas/{venta}/anular', [VentaController::class, 'anular']);
        Route::post('/reposterias/{reposteria}/ventas/{venta}/pagos', [PagoController::class, 'store']);
        Route::delete('/reposterias/{reposteria}/ventas/{venta}/pagos/{pago}', [PagoController::class, 'destroy']);
        Route::get('/reposterias/{reposteria}/ingredientes', [IngredienteController::class, 'index']);
        Route::get('/reposterias/{reposteria}/ingredientes/{ingrediente}', [IngredienteController::class, 'show']);
        Route::post('/reposterias/{reposteria}/ingredientes', [IngredienteController::class, 'store']);
        Route::patch('/reposterias/{reposteria}/ingredientes/{ingrediente}', [IngredienteController::class, 'update']);
        Route::get('/reposterias/{reposteria}/recetas', [RecetaController::class, 'index']);
        Route::get('/reposterias/{reposteria}/recetas/{receta}', [RecetaController::class, 'show']);
        Route::post('/reposterias/{reposteria}/recetas', [RecetaController::class, 'store']);
        Route::patch('/reposterias/{reposteria}/recetas/{receta}', [RecetaController::class, 'update']);
        Route::get('/reposterias/{reposteria}/inventario/movimientos', [MovimientoInventarioController::class, 'index']);
        Route::post('/reposterias/{reposteria}/inventario/movimientos', [MovimientoInventarioController::class, 'store']);
    });
});
