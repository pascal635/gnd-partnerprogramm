<?php

use App\Http\Controllers\Webhooks\ConversionWebhookController;
use App\Http\Controllers\Webhooks\LeadWebhookController;
use Illuminate\Support\Facades\Route;

// Inbound webhooks (HMAC-signed, stateless — no CSRF/session).
Route::post('/webhooks/wp/lead', LeadWebhookController::class)
    ->middleware('gnd.webhook:wp_lead');

Route::post('/webhooks/conversion', ConversionWebhookController::class)
    ->middleware('gnd.webhook:conversion');
