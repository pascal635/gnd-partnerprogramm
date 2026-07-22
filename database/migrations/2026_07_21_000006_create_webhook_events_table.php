<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            // wp_lead | zapier_conversion | wp_sync_ack | other (cast in model)
            $table->string('source', 30);
            $table->string('event_type', 64)->nullable();
            $table->string('external_event_id', 191)->nullable();
            // Derived stable key — the idempotency guard.
            $table->string('idempotency_key', 191)->unique();
            $table->boolean('signature_valid')->default(false);
            $table->string('source_ip', 45)->nullable();
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            // received | processed | failed | ignored_duplicate | invalid_signature
            $table->string('status', 30)->default('received');
            $table->text('processing_error')->nullable();
            // Loose links (no FK) — this is an append-only audit log.
            $table->unsignedBigInteger('related_lead_id')->nullable();
            $table->unsignedBigInteger('related_conversion_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->index(['source', 'status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
