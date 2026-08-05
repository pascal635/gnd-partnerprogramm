<?php

/**
 * Standalone-Diagnose (token-geschützt). Aufruf: /diag.php?token=<DEPLOY_TOKEN>.
 * Optional: &mailtest=1 sendet eine Test-Mail und zeigt den exakten SMTP-Fehler.
 * NACH der Fehlersuche wieder entfernen (per FTP löschen).
 */
$envPath = __DIR__.'/../.env';
$expected = '';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'DEPLOY_TOKEN=')) {
            $expected = trim(substr($line, strlen('DEPLOY_TOKEN=')));
            break;
        }
    }
}

if ($expected === '' || ! hash_equals($expected, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$mask = static function (?string $v): string {
    $v = (string) $v;

    return $v === '' ? '(leer)' : 'gesetzt, Länge '.strlen($v);
};

$unwrap = static function (\Throwable $e): string {
    $msgs = [];
    $cur = $e;
    while ($cur) {
        $msgs[] = get_class($cur).': '.$cur->getMessage();
        $cur = $cur->getPrevious();
    }

    return implode("\n   ⤷ ", $msgs);
};

echo 'PHP '.PHP_VERSION."   |   .env: ".(is_file($envPath) ? 'ja' : 'NEIN')."\n";

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "Boot OK\n";

try {
    DB::connection()->getPdo();
    echo "DB OK\n";
} catch (\Throwable $e) {
    echo 'DB-FEHLER: '.$e->getMessage()."\n";
}

echo "\n--- Mail-Konfiguration ---\n";
$smtp = config('mail.mailers.smtp');
echo 'Mailer: '.config('mail.default')."\n";
echo 'Host: '.($smtp['host'] ?? '').'   Port: '.($smtp['port'] ?? '')."\n";
echo 'Scheme/Encryption: '.(($smtp['scheme'] ?? $smtp['encryption'] ?? '') ?: '(keine)')."\n";
echo 'Username: '.($smtp['username'] ?? '(leer)')."\n";
echo 'Password: '.$mask($smtp['password'] ?? '')."\n";
echo 'From: '.config('mail.from.address')."\n";

echo "\n--- WordPress-Voucher-Endpunkt ---\n";
try {
    echo 'Endpoint: '.(\App\Support\Secrets::wpVoucherEndpoint() ?: '(nicht konfiguriert)')."\n";
    echo 'Secret: '.$mask(\App\Support\Secrets::wpSyncSecret())."\n";
} catch (\Throwable $e) {
    echo 'Secrets-FEHLER: '.$e->getMessage()."\n";
}

if (($_GET['mailtest'] ?? '') === '1') {
    echo "\n--- Live-Mailtest ---\n";
    $to = (string) (config('mail.from.address') ?: $smtp['username'] ?? '');
    echo "Sende Test an: {$to}\n";
    try {
        \Illuminate\Support\Facades\Mail::raw('GND SMTP-Test '.date('H:i:s'), function ($m) use ($to) {
            $m->to($to)->subject('GND SMTP-Test');
        });
        echo "ERGEBNIS: Versand OK (keine Exception)\n";
    } catch (\Throwable $e) {
        echo "ERGEBNIS: FEHLER\n   ".$unwrap($e)."\n";
    }
}

echo "\n--- Log: nur Fehlermeldungen (letzte 15) ---\n";
$log = __DIR__.'/../storage/logs/laravel.log';
if (is_file($log)) {
    $lines = @file($log, FILE_IGNORE_NEW_LINES) ?: [];
    $errs = array_values(array_filter($lines, static fn ($l) => str_contains($l, '.ERROR:') || str_contains($l, '.CRITICAL:')));
    foreach (array_slice($errs, -15) as $l) {
        echo mb_substr($l, 0, 400)."\n";
    }
    if ($errs === []) {
        echo "(keine ERROR-Zeilen)\n";
    }
} else {
    echo "(kein Log gefunden)\n";
}
