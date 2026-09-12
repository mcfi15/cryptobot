<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\ScannerController;
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

    Route::prefix('scanner')->name('scanner.')->group(function () {
        Route::get('/', [ScannerController::class, 'index'])->name('index');
        Route::post('/start', [ScannerController::class, 'start'])->name('start');
        Route::post('/stop', [ScannerController::class, 'stop'])->name('stop');
        Route::post('/config', [ScannerController::class, 'updateConfig'])->name('config.update');
        Route::post('/run', [ScannerController::class, 'runScan'])->name('run');
        Route::get('/refresh', [ScannerController::class, 'refresh'])->name('refresh');

        Route::post('/watchlist', [ScannerController::class, 'watchlistStore'])->name('watchlist.store');
        Route::delete('/watchlist/{entry}', [ScannerController::class, 'watchlistDestroy'])->name('watchlist.destroy');
        Route::patch('/watchlist/{entry}/toggle', [ScannerController::class, 'watchlistToggle'])->name('watchlist.toggle');
        Route::post('/watchlist/reorder', [ScannerController::class, 'reorderWatchlist'])->name('watchlist.reorder');

        Route::get('/signals/{signal}', [ScannerController::class, 'showSignal'])->name('signal.show');
        Route::get('/signals/{signal}/confirm', [ScannerController::class, 'tradeConfirm'])->name('signal.confirm');
        Route::post('/signals/{signal}/trade', [ScannerController::class, 'trade'])->name('signal.trade');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [AdminAuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('settings', [AdminSettingController::class, 'edit'])->name('settings.edit');
        Route::post('settings', [AdminSettingController::class, 'update'])->name('settings.update');
    });

    Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');
});
