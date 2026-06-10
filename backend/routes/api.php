<?php

declare(strict_types=1);

use App\Http\Controllers\BrandController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderTypeController;
use App\Http\Controllers\ServingTimesController;
use App\Http\Controllers\StoreHoursController;
use App\Http\Controllers\VenueController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => response()->json(['status' => 'ok']));

// Legacy store hours (original scaffold)
Route::prefix('stores/{storeId}')->group(function () {
    Route::get('/hours', [StoreHoursController::class, 'show']);
    Route::post('/hours/parse', [StoreHoursController::class, 'parse']);
    Route::patch('/hours', [StoreHoursController::class, 'update']);
});

// Brands
Route::apiResource('brands', BrandController::class);

// Venues scoped under brand
Route::prefix('brands/{brand}')->group(function () {
    Route::apiResource('venues', VenueController::class);

    Route::prefix('venues/{venue}')->group(function () {
        Route::get('order-types', [OrderTypeController::class, 'venueOrderTypes']);
        Route::post('order-types', [OrderTypeController::class, 'attachToVenue']);
        Route::delete('order-types/{orderTypeId}', [OrderTypeController::class, 'detachFromVenue']);

        Route::apiResource('menus', MenuController::class);
    });
});

// Global order types list
Route::get('order-types', [OrderTypeController::class, 'index']);

// Serving times — parse + replace must come before the wildcard routes
Route::post('serving-times/parse', [ServingTimesController::class, 'parse']);
Route::put('serving-times/replace', [ServingTimesController::class, 'replace']);

Route::get('serving-times', [ServingTimesController::class, 'index']);
Route::post('serving-times', [ServingTimesController::class, 'store']);
Route::put('serving-times/{servingTime}', [ServingTimesController::class, 'update']);
Route::delete('serving-times/{servingTime}', [ServingTimesController::class, 'destroy']);
