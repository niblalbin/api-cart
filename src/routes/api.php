<?php

use App\Http\Controllers\API\CartItemController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\ProductController;

// Rotte pubbliche
Route::post('login', [AuthController::class, 'login']);

// Rotte private (richiedono autenticazione)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
    
    // Products
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    
    // Carts
    Route::post('carts', [CartController::class, 'store']);
    Route::get('carts', [CartController::class, 'index']);
    Route::get('carts/{id}', [CartController::class, 'show']);
    Route::post('carts/{id}/items', [CartItemController::class, 'store']);
    Route::post('carts/{id}/checkout', [CartController::class, 'checkout']);
});