<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\WebhookSource;
use App\Enums\WebhookStatus;
use App\Models\WebhookEvent;
use App\Services\Ingest\ConversionIngestService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ConversionWebhookController
{
    public function __construct(private readonly ConversionIngestService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = (array) $request->json()->all();
        $dealId = $payload['deal_id'] ?? null;

        if (blank($dealId)) {
            return response()->json(['status' => 'error', 'message' => 'deal_id fehlt'], 422);
        }

        try {
            $event = WebhookEvent::create([
                'source' => WebhookSource::ZapierConversion,
                'event_type' => $payload['event'] ?? null,
                'external_event_id' => (string) $dealId,
                'idempotency_key' => 'conversion:'.$dealId,
                'signature_valid' => true,
                'source_ip' => $request->ip(),
                'payload' => $payload,
                'status' => WebhookStatus::Received,
                'received_at' => now(),
            ]);
        } catch (QueryException) {
            return response()->json(['status' => 'duplicate_ignored'], 200);
        }

        try {
            $conversion = $this->service->ingest($payload);
            $event->update([
                'status' => WebhookStatus::Processed,
                'processed_at' => now(),
                'related_conversion_id' => $conversion->id,
                'related_lead_id' => $conversion->lead_id,
            ]);

            return response()->json(['status' => 'ok', 'deal_id' => $conversion->external_deal_id], 202);
        } catch (Throwable $e) {
            $event->update([
                'status' => WebhookStatus::Failed,
                'processing_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            report($e);

            return response()->json(['status' => 'error', 'message' => 'Verarbeitung fehlgeschlagen'], 200);
        }
    }
}
