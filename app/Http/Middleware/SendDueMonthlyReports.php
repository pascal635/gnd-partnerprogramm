<?php

namespace App\Http\Middleware;

use App\Services\Reporting\MonthlyReportSender;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * „Cron-Ersatz" ohne Daemon: Prüft bei Admin-Aufrufen, ob der Monatsreport
 * fällig ist, und versendet ihn einmalig. Läuft in terminate() – also NACH
 * dem Ausliefern der Antwort, damit die Seite nicht wartet.
 */
class SendDueMonthlyReports
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            app(MonthlyReportSender::class)->runIfDue();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
