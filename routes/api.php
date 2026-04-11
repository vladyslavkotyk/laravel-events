<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public product browsing, authenticated order management, and
| third-party webhooks for payment / shipping status updates.
|
*/

// Public
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);
});

// Webhooks (verified by signature, not session auth)
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [WebhookController::class, 'stripe']);
    Route::post('/shipping', [WebhookController::class, 'shipping']);
});
