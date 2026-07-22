<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Shared-Hosting hat keinen Dauer-Worker: die Warteschlange wird per Minuten-Cron
// (`php artisan schedule:run`) verarbeitet. Verarbeitet u. a. den WordPress-
// Gutschein-Sync inkl. Retries.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=8')
    ->everyMinute()
    ->withoutOverlapping();
