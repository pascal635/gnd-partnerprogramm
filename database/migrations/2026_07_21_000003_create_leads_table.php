<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            // Stable WP lead id — the golden matching key. Unique (NULLs allowed).
            $table->string('external_lead_id', 191)->nullable()->unique();
            $table->foreignId('voucher_code_id')->nullable()->constrained('voucher_codes')->nullOnDelete();
            // Raw code string from payload (pre-resolution / if code unknown).
            $table->string('voucher_code_raw', 64)->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('voucher_partner_raw', 191)->nullable();
            // SNAPSHOT of the commission terms at lead time (immune to later edits).
            $table->string('voucher_commission_raw', 50)->nullable();
            // Customer PII — stored (GND is controller), NEVER shown to partners.
            $table->string('customer_email', 191)->nullable();
            $table->string('customer_email_norm', 191)->nullable();
            $table->string('customer_name', 191)->nullable();
            $table->string('customer_phone', 64)->nullable();
            $table->string('property_type', 64)->nullable();
            $table->string('plz', 16)->nullable();
            $table->string('ort', 191)->nullable();
            // new | converted | rejected | expired (cast in model)
            $table->string('status', 20)->default('new');
            // Created by an early conversion (conversion-before-lead race).
            $table->boolean('is_stub')->default(false);
            // webhook | manual | stub (cast in model)
            $table->string('source', 20)->default('webhook');
            $table->timestamp('submitted_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('customer_email_norm');
            $table->index('partner_id');
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
