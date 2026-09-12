<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('CryptoBot Trading Platform');
})->purpose('Display platform info');

Schedule::command('trading:health')->hourly();
Schedule::command('trading:run-signals')->everyFiveMinutes();
Schedule::command('trading:sync-markets')->daily();

Schedule::command('scanner:run')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('scanner:monitor')->everyMinute()->withoutOverlapping();
