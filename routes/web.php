<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\BotController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('login'));

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('exchanges', ExchangeController::class)->except(['edit', 'update', 'show']);
    Route::post('exchanges/{exchange}/test', [ExchangeController::class, 'test'])->name('exchanges.test');
    Route::post('exchanges/{exchange}/toggle-trading', [ExchangeController::class, 'toggleTrading'])->name('exchanges.toggle');

    Route::resource('bots', BotController::class);
    Route::post('bots/{bot}/start', [BotController::class, 'start'])->name('bots.start');
    Route::post('bots/{bot}/stop', [BotController::class, 'stop'])->name('bots.stop');
    Route::post('bots/{bot}/pause', [BotController::class, 'pause'])->name('bots.pause');
    Route::get('bots/{bot}/signals', [BotController::class, 'signals'])->name('bots.signals');
    Route::get('bots/{bot}/trades', [BotController::class, 'trades'])->name('bots.trades');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
