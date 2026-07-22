<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            // sachverstaendiger | steuerberater | makler | sonstige (cast in model)
            $table->string('partner_type', 30)->default('sonstige');
            $table->string('contact_person')->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('street')->nullable();
            $table->string('zip', 16)->nullable();
            $table->string('city')->nullable();
            $table->char('country', 2)->default('DE');
            $table->string('vat_id', 32)->nullable();
            // Encrypted at rest via the model's 'encrypted' cast (Phase 2 payouts).
            $table->text('iban_encrypted')->nullable();
            // Optional default provision applied to new codes, e.g. '10%' or '150'.
            $table->string('default_commission_raw', 50)->nullable();
            // active | inactive (cast in model)
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
