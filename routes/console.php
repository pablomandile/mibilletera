<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Genera las transacciones recurrentes cada día a las 00:05.
// En producción: correr `php artisan schedule:work` o un cron con `schedule:run`.
Schedule::command('recurring:run')->dailyAt('00:05');

// Genera las alertas de recordatorios (campanita) cada minuto.
Schedule::command('reminders:dispatch')->everyMinute();
