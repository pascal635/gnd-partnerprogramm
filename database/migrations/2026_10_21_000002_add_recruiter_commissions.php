<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A partner can be recruited by another partner, who then earns a
        // configurable extra provision on the recruit's orders (one level).
        Schema::table('partners', function (Blueprint $table) {
            $table->foreignId('recruited_by_partner_id')->nullable()->after('partner_type')->constrained('partners')->nullOnDelete();
            // e.g. "5%" or "50"; empty = global default (Setting commission.recruiter_default).
            $table->string('recruiter_commission_raw', 50)->nullable()->after('recruited_by_partner_id');
        });

        // Up to two commissions per conversion: direct + recruiter.
        Schema::table('commissions', function (Blueprint $table) {
            $table->string('tier', 20)->default('direct')->after('conversion_id');
            $table->foreignId('source_partner_id')->nullable()->after('partner_id')->constrained('partners')->nullOnDelete();
        });

        Schema::table('commissions', function (Blueprint $table) {
            // Add the composite index first: it keeps conversion_id indexed for its FK.
            $table->unique(['conversion_id', 'tier']);
            $table->dropUnique(['conversion_id']);
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->unique(['conversion_id']);
            $table->dropUnique(['conversion_id', 'tier']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_partner_id');
            $table->dropColumn('tier');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recruited_by_partner_id');
            $table->dropColumn('recruiter_commission_raw');
        });
    }
};
