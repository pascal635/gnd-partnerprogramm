<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Integration secrets/endpoints, editable in the dashboard (settings table)
 * with a fallback to .env config. This lets staff manage them from the UI
 * instead of editing .env on shared hosting.
 */
class Secrets
{
    public static function wpLeadSecret(): string
    {
        return (string) (Setting::get('wp_lead_secret') ?: config('gnd.webhooks.lead_secret'));
    }

    public static function conversionSecret(): string
    {
        return (string) (Setting::get('conversion_secret') ?: config('gnd.webhooks.conversion_secret'));
    }

    public static function wpSyncSecret(): string
    {
        return (string) (Setting::get('wp_sync_secret') ?: config('gnd.wp.sync_secret'));
    }

    public static function wpVoucherEndpoint(): string
    {
        return (string) (Setting::get('wp_voucher_endpoint') ?: config('gnd.wp.voucher_endpoint'));
    }

    public static function pipedriveLeadIdField(): string
    {
        return (string) (Setting::get('pipedrive_lead_id_field') ?: config('gnd.pipedrive.lead_id_field') ?: 'GND Lead-ID');
    }
}
