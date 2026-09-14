<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TradingApiController;
use App\Http\Controllers\Api\ScannerApiController;

Route::middleware(['auth'])->prefix('v1')->group(function () {
    Route::get('/signals', [TradingApiController::class, 'getSignals']);
    Route::get('/positions', [TradingApiController::class, 'getPositions']);
    Route::get('/trades', [TradingApiController::class, 'getTrades']);
    Route::get('/performance', [TradingApiController::class, 'getPerformance']);

    Route::post('/exchanges/{exchange}/test', [TradingApiController::class, 'testExchange']);
    Route::get('/exchanges/{exchange}/balances', [TradingApiController::class, 'getBalances']);
    Route::get('/exchanges/{exchange}/markets', [TradingApiController::class, 'getMarkets']);
});

Route::middleware(['auth'])->prefix('v1/scanner')->name('scanner.')->group(function () {
    Route::get('/status', [ScannerApiController::class, 'status']);
    Route::get('/signals', [ScannerApiController::class, 'signals']);
    Route::get('/signals/{signal}', [ScannerApiController::class, 'signal']);
    Route::post('/run', [ScannerApiController::class, 'run']);
    Route::post('/signals/{signal}/trade', [ScannerApiController::class, 'trade']);
    Route::post('/signals/{signal}/watch', [ScannerApiController::class, 'watch']);
    Route::post('/signals/{signal}/unwatch', [ScannerApiController::class, 'unwatch']);
    Route::post('/signals/{signal}/dismiss', [ScannerApiController::class, 'dismiss']);
    Route::get('/watchlist', [ScannerApiController::class, 'watchlist']);
    Route::get('/activity', [ScannerApiController::class, 'activity']);
});
