<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RentalBlockController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/products/{product}/availability', [RentalBlockController::class, 'availability']);
    Route::get('/categories', [CategoryController::class, 'index']);

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

            Route::get('/products/{product}/rental-history', [RentalBlockController::class, 'productHistory']);
            Route::get('/rental-blocks', [RentalBlockController::class, 'index']);
            Route::post('/rental-blocks', [RentalBlockController::class, 'store']);
            Route::patch('/rental-blocks/{rentalBlock}', [RentalBlockController::class, 'update']);
            Route::delete('/rental-blocks/{rentalBlock}', [RentalBlockController::class, 'destroy']);

            Route::get('/customers', [CustomerController::class, 'index']);
            Route::get('/customers/{customer}', [CustomerController::class, 'show']);
            Route::post('/customers', [CustomerController::class, 'store']);
            Route::patch('/customers/{customer}', [CustomerController::class, 'update']);
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
        });
    });
});
