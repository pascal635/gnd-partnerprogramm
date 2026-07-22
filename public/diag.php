<?php

/**
 * Standalone-Diagnose (bootet Laravel NICHT direkt), token-geschützt.
 * Aufruf: /diag.php?token=<DEPLOY_TOKEN>. Nach der Fehlersuche wieder entfernen.
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

echo 'PHP-Version: '.PHP_VERSION."\n";
foreach (['intl', 'pdo_mysql', 'mbstring', 'openssl', 'zip', 'gd', 'curl', 'fileinfo', 'dom'] as $ext) {
    echo "  ext {$ext}: ".(extension_loaded($ext) ? 'ja' : 'NEIN')."\n";
}
echo 'storage schreibbar: '.(is_writable(__DIR__.'/../storage') ? 'ja' : 'NEIN')."\n";
echo 'bootstrap/cache schreibbar: '.(is_writable(__DIR__.'/../bootstrap/cache') ? 'ja' : 'NEIN')."\n";
echo '.env vorhanden: '.(is_file($envPath) ? 'ja' : 'NEIN')."\n";

echo "\n--- Laravel-Boot-Test ---\n";
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
} catch (\Throwable $e) {
    echo 'BOOT-FEHLER: '.get_class($e).': '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}
