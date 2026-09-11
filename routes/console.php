<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('CryptoBot Trading Platform');
})->purpose('Display platform info');

Schedule::command('trading:health')->hourly();
Schedule::command('trading:run-signals')->everyFiveMinutes();
Schedule::command('trading:sync-markets')->daily();
