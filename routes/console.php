<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('device:mqtt-listen --max-seconds=55')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(static fn (): bool => config('services.mqtt.host') !== '');

// Verifica (e crea se mancante) il dispositivo ESP32-PILL-001 all'avvio
// e poi ogni notte, cosi' il dispositivo e' sempre presente nel DB.
Schedule::command('device:ensure-esp32')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->when(static fn (): bool => config('services.mqtt.host') !== '');
