<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer belongs for life to the partner who brought them first
        // (first-touch). Recognised by email (WP form) or Pipedrive person id.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('email_norm', 191)->nullable()->unique();
            $table->string('pipedrive_person_id', 64)->nullable()->unique();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            // Original code: supplies the commission terms for repeat orders.
            $table->foreignId('voucher_code_id')->nullable()->constrained('voucher_codes')->nullOnDelete();
            $table->foreignId('first_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('partner_id')->constrained('customers')->nullOnDelete();
            // voucher | customer | manual (cast in model)
            $table->string('attributed_via', 20)->nullable()->after('customer_id');
        });

        Schema::table('conversions', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('lead_id')->constrained('customers')->nullOnDelete();
            $table->string('pipedrive_person_id', 64)->nullable()->after('customer_id')->index();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropIndex(['pipedrive_person_id']);
            $table->dropColumn('pipedrive_person_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('attributed_via');
        });

        Schema::dropIfExists('customers');
    }

    /** Idempotent: builds customers from existing leads (owner = earliest attributed lead). */
    private function backfill(): void
    {
        $emails = DB::table('leads')
            ->whereNotNull('customer_email_norm')
            ->where('customer_email_norm', '!=', '')
            ->whereNull('customer_id')
            ->distinct()
            ->pluck('customer_email_norm');

        foreach ($emails as $email) {
            $leads = DB::table('leads')
                ->where('customer_email_norm', $email)
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->get(['id', 'partner_id', 'voucher_code_id']);

            $owner = $leads->first(fn ($lead) => $lead->partner_id !== null);

            $customerId = DB::table('customers')->where('email_norm', $email)->value('id')
                ?? DB::table('customers')->insertGetId([
                    'email_norm' => $email,
                    'partner_id' => $owner?->partner_id,
                    'voucher_code_id' => $owner?->voucher_code_id,
                    'first_lead_id' => $leads->first()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('leads')->where('customer_email_norm', $email)->update(['customer_id' => $customerId]);
        }

        DB::table('leads')
            ->whereNull('attributed_via')
            ->whereNotNull('partner_id')
            ->update(['attributed_via' => 'voucher']);

        DB::table('conversions')
            ->whereNull('customer_id')
            ->whereNotNull('lead_id')
            ->update(['customer_id' => DB::raw('(select leads.customer_id from leads where leads.id = conversions.lead_id)')]);
    }
};
