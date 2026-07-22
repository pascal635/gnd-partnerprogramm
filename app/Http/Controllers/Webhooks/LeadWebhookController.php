<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\WebhookSource;
use App\Enums\WebhookStatus;
use App\Models\WebhookEvent;
use App\Services\Ingest\LeadIngestService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LeadWebhookController
{
    public function __construct(private readonly LeadIngestService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = (array) $request->json()->all();
        $leadId = $payload['lead_id'] ?? null;

        if (blank($leadId)) {
            return response()->json(['status' => 'error', 'message' => 'lead_id fehlt'], 422);
        }

        // Idempotency guard via the UNIQUE key. A duplicate insert => already seen.
        try {
            $event = WebhookEvent::create([
                'source' => WebhookSource::WpLead,
                'event_type' => $payload['event'] ?? null,
                'external_event_id' => (string) $leadId,
                'idempotency_key' => 'wp_lead:'.$leadId,
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
            $lead = $this->service->ingest($payload);
            $event->update([
                'status' => WebhookStatus::Processed,
                'processed_at' => now(),
                'related_lead_id' => $lead->id,
            ]);

            return response()->json(['status' => 'ok', 'lead_id' => $lead->external_lead_id], 202);
        } catch (Throwable $e) {
            // Soft-fail: record for the admin "Fehlgeschlagene Webhooks" queue,
            // return 200 so the sender does not enter a retry storm.
            $event->update([
                'status' => WebhookStatus::Failed,
                'processing_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            report($e);

            return response()->json(['status' => 'error', 'message' => 'Verarbeitung fehlgeschlagen'], 200);
        }
    }
}
