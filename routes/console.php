<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hinweis: WordPress-Sync und Willkommens-Mail laufen synchron beim Speichern –
// dafür ist KEIN Cron nötig. Dieser Zeitplan ist nur ein optionales Sicherheitsnetz:
// Wer einen Minuten-Cron (`php artisan schedule:run`) einrichtet, lässt damit ggf.
// zurückgestellte Queue-Jobs nachlaufen. Ohne Cron passiert hier nichts (unschädlich).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=8')
    ->everyMinute()
    ->withoutOverlapping();
