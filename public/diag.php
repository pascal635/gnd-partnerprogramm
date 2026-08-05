<?php

/**
 * Standalone-Diagnose (token-geschützt). Aufruf: /diag.php?token=<DEPLOY_TOKEN>.
 * Zeigt Umgebung, DB-Verbindung, Mail-/WP-Konfiguration (Passwort maskiert),
 * einfache Erreichbarkeitstests und die letzten Log-Zeilen.
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
    if ($v === '') {
        return '(leer)';
    }

    return 'gesetzt, Länge '.strlen($v).', beginnt mit "'.substr($v, 0, 2).'…"';
};

echo 'PHP-Version: '.PHP_VERSION."\n";
foreach (['intl', 'pdo_mysql', 'mbstring', 'openssl', 'zip', 'gd', 'curl', 'fileinfo', 'dom'] as $ext) {
    echo "  ext {$ext}: ".(extension_loaded($ext) ? 'ja' : 'NEIN')."\n";
}
echo '.env vorhanden: '.(is_file($envPath) ? 'ja' : 'NEIN')."\n";

echo "\n--- Laravel-Boot ---\n";
try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo "Boot OK\n";

    try {
        DB::connection()->getPdo();
        echo "DB-Verbindung OK\n";
    } catch (\Throwable $e) {
        echo 'DB-FEHLER: '.$e->getMessage()."\n";
    }

    echo "\n--- Mail-Konfiguration ---\n";
    $mailer = config('mail.default');
    echo 'MAIL_MAILER: '.$mailer."\n";
    $smtp = config('mail.mailers.smtp');
    $host = $smtp['host'] ?? '';
    $port = $smtp['port'] ?? '';
    echo 'Host: '.$host."\n";
    echo 'Port: '.$port."\n";
    echo 'Encryption/Scheme: '.(($smtp['encryption'] ?? $smtp['scheme'] ?? '') ?: '(keine)')."\n";
    echo 'Username: '.($smtp['username'] ?? '(leer)')."\n";
    echo 'Password: '.$mask($smtp['password'] ?? '')."\n";
    echo 'From: '.config('mail.from.address').' ('.config('mail.from.name').")\n";

    if ($mailer === 'smtp' && $host && $port) {
        echo "SMTP erreichbar? ";
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen((str_contains((string) ($smtp['encryption'] ?? ''), 'ssl') ? 'ssl://' : '').$host, (int) $port, $errno, $errstr, 8);
        if ($fp) {
            echo "ja (TCP-Verbindung steht)\n";
            fclose($fp);
        } else {
            echo "NEIN – {$errno} {$errstr}\n";
        }
    }

    echo "\n--- WordPress-Voucher-Endpunkt ---\n";
    try {
        echo 'Endpoint: '.(\App\Support\Secrets::wpVoucherEndpoint() ?: '(nicht konfiguriert)')."\n";
        echo 'Secret: '.$mask(\App\Support\Secrets::wpSyncSecret())."\n";
    } catch (\Throwable $e) {
        echo 'Secrets-FEHLER: '.$e->getMessage()."\n";
    }
} catch (\Throwable $e) {
    echo 'BOOT-FEHLER: '.get_class($e).': '.$e->getMessage()."\n";
}

echo "\n--- Letzte Log-Zeilen (storage/logs/laravel.log) ---\n";
$log = __DIR__.'/../storage/logs/laravel.log';
if (is_file($log)) {
    $lines = @file($log, FILE_IGNORE_NEW_LINES) ?: [];
    foreach (array_slice($lines, -60) as $l) {
        echo $l."\n";
    }
} else {
    echo "(kein Log gefunden)\n";
}
