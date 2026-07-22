<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_codes', function (Blueprint $table) {
            $table->id();
            // NULL = non-partner promo code (e.g. SOMMER10).
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('code', 64)->unique();
            // Discount TYP, WP-compatible: prozent | fix (cast in model)
            $table->string('type', 10);
            // Discount WERT
            $table->decimal('value', 12, 2);
            // PARTNER free-text label as it appears in WP
            $table->string('partner_label', 191)->nullable();
            // PROVISION verbatim, source of truth: '150' or '10%'
            $table->string('commission_raw', 50)->nullable();
            // Parsed from commission_raw: fix | percent | none (cast in model)
            $table->string('commission_kind', 10)->default('none');
            $table->decimal('commission_value', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            // Outbound WP sync state: pending | synced | failed (cast in model)
            $table->string('sync_status', 10)->default('pending');
            $table->timestamp('synced_to_wp_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_codes');
    }
};
