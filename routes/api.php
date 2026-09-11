<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TradingApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('/signals', [TradingApiController::class, 'getSignals']);
    Route::get('/positions', [TradingApiController::class, 'getPositions']);
    Route::get('/trades', [TradingApiController::class, 'getTrades']);
    Route::get('/performance', [TradingApiController::class, 'getPerformance']);

    Route::post('/exchanges/{exchange}/test', [TradingApiController::class, 'testExchange']);
    Route::get('/exchanges/{exchange}/balances', [TradingApiController::class, 'getBalances']);
    Route::get('/exchanges/{exchange}/markets', [TradingApiController::class, 'getMarkets']);
});
