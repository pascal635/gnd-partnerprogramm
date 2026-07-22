<?php

use App\Http\Controllers\DeployController;
use Illuminate\Support\Facades\Route;

// Landing → Admin-Login (kein Closure, damit route:cache funktioniert).
Route::redirect('/', '/admin');

// Geschützte Deploy-Route (FTP-Hosting ohne SSH): Migrationen + Cache per URL.
// Deaktiviert (404), wenn DEPLOY_TOKEN nicht gesetzt ist.
Route::get('/gnd-deploy/{token}', DeployController::class);
