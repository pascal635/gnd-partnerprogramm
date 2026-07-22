<?php

use Illuminate\Support\Facades\Route;

// Landing → Admin-Login (kein Closure, damit route:cache funktioniert).
Route::redirect('/', '/admin');

// Die Deploy-Route (/gnd-deploy/{token}) ist bewusst ohne web-Middleware
// (kein Session/DB-Zwang) in bootstrap/app.php registriert.
