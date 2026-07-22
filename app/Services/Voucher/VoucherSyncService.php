<?php

namespace App\Services\Voucher;

use App\Enums\OutboundStatus;
use App\Enums\SyncStatus;
use App\Models\OutboundWebhook;
use App\Models\VoucherCode;
use App\Support\Secrets;
use App\Support\WebhookSignature;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Registers/updates a voucher code in the WordPress plugin via an HMAC-signed,
 * idempotent upsert. Throws on transient failure so the queued job can retry;
 * records every attempt in outbound_webhooks for the Integrations inspector.
 */
class VoucherSyncService
{
    public function push(VoucherCode $voucher): void
    {
        // UI-configurable (Integrations page) with .env fallback.
        $endpoint = Secrets::wpVoucherEndpoint();
        $secret = Secrets::wpSyncSecret();

        $payload = $this->payload($voucher);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $outbound = OutboundWebhook::updateOrCreate(
            ['idempotency_key' => "voucher:{$voucher->id}"],
            [
                'voucher_code_id' => $voucher->id,
                'endpoint' => $endpoint ?: '(nicht konfiguriert)',
                'payload' => $payload,
                'last_attempt_at' => now(),
            ],
        );
        $outbound->increment('attempt_count');

        if (blank($endpoint)) {
            $voucher->update(['sync_status' => SyncStatus::Failed]);
            $outbound->update([
                'status' => OutboundStatus::Failed,
                'response_body' => 'WP_VOUCHER_ENDPOINT ist nicht konfiguriert.',
            ]);

            return;
        }

        $timestamp = (string) time();

        try {
            $response = Http::withHeaders([
                'X-GND-Signature' => WebhookSignature::header($secret, $timestamp, $body),
                'X-GND-Timestamp' => $timestamp,
            ])
                ->withBody($body, 'application/json')
                ->timeout((int) config('gnd.wp.timeout', 10))
                ->post($endpoint);
        } catch (Throwable $e) {
            $outbound->update([
                'status' => OutboundStatus::Failed,
                'response_body' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }

        if ($response->successful()) {
            $voucher->update([
                'sync_status' => SyncStatus::Synced,
                'synced_to_wp_at' => now(),
            ]);
            $outbound->update([
                'status' => OutboundStatus::Acknowledged,
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 1000),
            ]);

            return;
        }

        $outbound->update([
            'status' => OutboundStatus::Sent,
            'response_code' => $response->status(),
            'response_body' => mb_substr($response->body(), 0, 1000),
        ]);

        throw new RuntimeException("WP voucher sync failed with HTTP {$response->status()}");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(VoucherCode $voucher): array
    {
        return [
            'code' => $voucher->code,
            'typ' => $voucher->type->value,
            'wert' => $this->formatValue($voucher->value),
            'partner' => $voucher->partner_label ?? '',
            'provision' => $voucher->commission_raw ?? '',
            'active' => (bool) $voucher->is_active,
            'valid_from' => $voucher->valid_from?->toDateString(),
            'valid_until' => $voucher->valid_until?->toDateString(),
            'voucher_line' => $voucher->voucherLine(),
        ];
    }

    private function formatValue(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
