<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            // Filled once matched (or linked to a stub).
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            // Pipedrive deal id — stable. Unique for idempotency.
            $table->string('external_deal_id', 191)->nullable()->unique();
            // Echoed WP lead id if threaded through (primary match key).
            $table->string('external_lead_id', 191)->nullable();
            $table->string('customer_email_norm', 191)->nullable();
            // Sold Gutachten value (for percent commission).
            $table->decimal('deal_value', 12, 2)->nullable();
            $table->char('deal_currency', 3)->default('EUR');
            $table->timestamp('converted_at')->nullable();
            // external_lead_id | deal_id | email | manual | unmatched (cast in model)
            $table->string('matched_by', 30)->default('unmatched');
            // high | medium | low (cast in model)
            $table->string('match_confidence', 10)->nullable();
            // matched | unmatched | needs_review (cast in model)
            $table->string('status', 20)->default('unmatched');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('lead_id');
            $table->index('external_lead_id');
            $table->index('customer_email_norm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversions');
    }
};
