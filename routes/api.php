<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe_webhook', StripeWebhookController::class);

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/customer/login', [AuthController::class, 'customerLogin']);
    Route::post('/stripe/webhook', StripeWebhookController::class);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/orders/checkout-session', [OrderController::class, 'createCheckoutSession']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware('role:admin')->group(function (): void {
            Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

            Route::apiResource('products', ProductController::class)->except(['index', 'show']);
            Route::patch('/products/{product}/status', [ProductController::class, 'updateStatus']);
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::patch('/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

            Route::apiResource('orders', OrderController::class)->except(['destroy']);
            Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);
            Route::post('/orders/{order}/installment-checkout-session', [OrderController::class, 'createInstallmentCheckoutSession']);
            Route::post('/orders/{order}/shipping-checkout-session', [OrderController::class, 'createShippingCheckoutSession']);
            Route::post('/orders/{order}/assign-customer', [OrderController::class, 'assignCustomer']);
        });

        Route::get('/customer/orders', [OrderController::class, 'customerIndex']);
    });
});
