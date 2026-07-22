<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * FTP-only hosting has no SSH — this route runs migrations and rebuilds caches
 * after an FTP upload, gated by a long secret token (DEPLOY_TOKEN). If the token
 * is not configured, the route 404s (disabled). Keep the token secret.
 */
class DeployController extends Controller
{
    public function __invoke(string $token): JsonResponse
    {
        $expected = (string) config('gnd.deploy_token');

        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        $steps = [
            'migrate' => ['--force' => true],
            'config:cache' => [],
            'route:cache' => [],
            'view:cache' => [],
            'filament:assets' => [],
            'storage:link' => [],
        ];

        $result = [];

        foreach ($steps as $command => $params) {
            try {
                Artisan::call($command, $params);
                $result[$command] = trim(Artisan::output()) ?: 'OK';
            } catch (Throwable $e) {
                $result[$command] = 'FEHLER: '.$e->getMessage();
            }
        }

        return response()->json(['status' => 'done', 'steps' => $result]);
    }
}
