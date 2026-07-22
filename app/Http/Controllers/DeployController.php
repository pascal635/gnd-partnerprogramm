<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * FTP-only hosting has no SSH — this route runs migrations, seeds roles, ensures
 * an admin user, and rebuilds caches after an FTP upload. Gated by a long secret
 * token (DEPLOY_TOKEN); 404 (disabled) if the token is not configured.
 */
class DeployController extends Controller
{
    public function __invoke(string $token): JsonResponse
    {
        $expected = (string) config('gnd.deploy_token');

        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        // Read admin credentials BEFORE config:cache runs below.
        $adminEmail = (string) config('gnd.admin.email');
        $adminPassword = (string) config('gnd.admin.password');

        $result = [];

        // 1) Schema
        try {
            Artisan::call('migrate', ['--force' => true]);
            $result['migrate'] = trim(Artisan::output()) ?: 'OK';
        } catch (Throwable $e) {
            $result['migrate'] = 'FEHLER: '.$e->getMessage();
        }

        // 2) Roles (admin / employee / partner)
        try {
            Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
            $result['roles'] = 'OK';
        } catch (Throwable $e) {
            $result['roles'] = 'FEHLER: '.$e->getMessage();
        }

        // 3) Ensure an admin user exists (password only set on first creation).
        if ($adminEmail !== '' && $adminPassword !== '') {
            try {
                $user = User::firstOrCreate(
                    ['email' => $adminEmail],
                    ['name' => 'Admin', 'password' => Hash::make($adminPassword), 'is_active' => true],
                );
                $user->syncRoles('admin');
                $result['admin'] = "OK ({$adminEmail})";
            } catch (Throwable $e) {
                $result['admin'] = 'FEHLER: '.$e->getMessage();
            }
        } else {
            $result['admin'] = 'übersprungen (ADMIN_EMAIL/ADMIN_PASSWORD nicht gesetzt)';
        }

        // 4) Caches
        foreach (['config:cache', 'route:cache', 'view:cache', 'filament:assets', 'storage:link'] as $command) {
            try {
                Artisan::call($command);
                $result[$command] = trim(Artisan::output()) ?: 'OK';
            } catch (Throwable $e) {
                $result[$command] = 'FEHLER: '.$e->getMessage();
            }
        }

        return response()->json(['status' => 'done', 'steps' => $result]);
    }
}
