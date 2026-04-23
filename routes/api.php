<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe_webhook', StripeWebhookController::class);

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/stripe/webhook', StripeWebhookController::class);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::post('/orders/checkout-session', [OrderController::class, 'createCheckoutSession']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware('role:admin')->group(function (): void {
            Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

            Route::apiResource('products', ProductController::class)->except(['index', 'show']);
            Route::patch('/products/{product}/status', [ProductController::class, 'updateStatus']);

            Route::apiResource('orders', OrderController::class)->except(['destroy']);
            Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);
        });
    });
});
