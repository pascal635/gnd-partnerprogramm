<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared-Hosting ohne Queue-Worker: ALLE Jobs/Notifications synchron
        // ausführen – sonst bleiben z. B. Filaments (queued) Passwort-vergessen-
        // und E-Mail-Notifications ungesendet in der jobs-Tabelle liegen.
        config(['queue.default' => 'sync']);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
