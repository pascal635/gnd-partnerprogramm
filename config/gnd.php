<?php

return [
    // Outbound: register voucher codes in the WordPress plugin.
    'wp' => [
        'voucher_endpoint' => env('WP_VOUCHER_ENDPOINT'),
        'sync_secret' => env('WP_SYNC_SECRET'),
        'timeout' => (int) env('WP_HTTP_TIMEOUT', 10),
    ],

    // Inbound webhook shared secrets + anti-replay window (seconds).
    'webhooks' => [
        'lead_secret' => env('WP_LEAD_SECRET'),
        'conversion_secret' => env('CONVERSION_SECRET'),
        'replay_window' => (int) env('WEBHOOK_REPLAY_WINDOW', 300),
    ],

    // Pipedrive API (deal_value backfill for percentage commissions).
    'pipedrive' => [
        'api_token' => env('PIPEDRIVE_API_TOKEN'),
        'base_url' => env('PIPEDRIVE_BASE_URL', 'https://api.pipedrive.com/v1'),
        // Custom deal field key that carries the GND lead id.
        'lead_id_field' => env('PIPEDRIVE_LEAD_ID_FIELD'),
    ],

    // Reference id prefix shown to partners.
    'reference_prefix' => env('GND_REFERENCE_PREFIX', 'GND'),

    // Token for the protected deploy route (FTP hosting without SSH).
    // Empty => the route is disabled (404).
    'deploy_token' => env('DEPLOY_TOKEN'),
];
