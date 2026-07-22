<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            // One commission per conversion.
            $table->foreignId('conversion_id')->unique()->constrained('conversions')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('voucher_code_id')->nullable()->constrained('voucher_codes')->nullOnDelete();
            // Snapshot at calc time: fix | percent (cast in model)
            $table->string('commission_kind', 10)->nullable();
            // 150.00 (fix) or 10.00 (percent) — snapshot
            $table->decimal('commission_rate', 12, 2)->nullable();
            // deal_value used as base for percent
            $table->decimal('base_amount', 12, 2)->nullable();
            // Computed; NULL until computable
            $table->decimal('amount', 12, 2)->nullable();
            $table->char('currency', 3)->default('EUR');
            // pending_input | calculated | needs_review (cast in model)
            $table->string('calc_status', 20)->default('pending_input');
            // Payout lifecycle: pending | approved | paid | cancelled (cast in model)
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference', 191)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
