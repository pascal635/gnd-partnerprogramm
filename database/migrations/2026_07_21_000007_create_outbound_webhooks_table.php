<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_code_id')->constrained('voucher_codes')->cascadeOnDelete();
            $table->string('target', 20)->default('wp_plugin');
            $table->string('endpoint', 255);
            $table->json('payload')->nullable();
            $table->string('idempotency_key', 191);
            $table->integer('attempt_count')->default(0);
            $table->integer('max_attempts')->default(8);
            // pending | sent | acknowledged | failed (cast in model)
            $table->string('status', 20)->default('pending');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhooks');
    }
};
