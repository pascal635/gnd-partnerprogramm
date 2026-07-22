<?php

namespace App\Http\Middleware;

use App\Support\Secrets;
use App\Support\WebhookSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies HMAC-SHA256 signatures on inbound webhooks over the RAW body.
 * Usage: ->middleware('gnd.webhook:wp_lead' | 'gnd.webhook:conversion').
 * Secrets are resolved from the dashboard settings (with .env fallback).
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next, string $source): Response
    {
        $secret = match ($source) {
            'wp_lead' => Secrets::wpLeadSecret(),
            'conversion' => Secrets::conversionSecret(),
            default => '',
        };

        $window = (int) config('gnd.webhooks.replay_window', 300);

        $valid = filled($secret) && WebhookSignature::verify(
            $secret,
            $request->header('X-GND-Signature'),
            $request->header('X-GND-Timestamp'),
            $request->getContent(), // raw body — must be read before parsing
            $window,
        );

        abort_unless($valid, 401, 'Invalid or missing webhook signature.');

        return $next($request);
    }
}
