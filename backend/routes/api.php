<?php

declare(strict_types=1);

use App\Http\Controllers\StoreHoursController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => response()->json(['status' => 'ok']));

Route::prefix('stores/{storeId}')->group(function () {
    Route::get('/hours', [StoreHoursController::class, 'show']);
    Route::post('/hours/parse', [StoreHoursController::class, 'parse']);
    Route::patch('/hours', [StoreHoursController::class, 'update']);
});
