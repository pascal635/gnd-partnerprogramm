<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // Ansprechpartner aufgeteilt (Anrede/Vor-/Nachname).
            $table->string('salutation', 20)->nullable();
            $table->string('first_name', 191)->nullable();
            $table->string('last_name', 191)->nullable();
            // Bankverbindung für Provisions-Auszahlung (IBAN verschlüsselt via
            // vorhandene Spalte iban_encrypted).
            $table->string('account_holder', 191)->nullable();
            $table->string('bic', 32)->nullable();
            // Fachartikel-Link, den der Partner auf seiner Seite veröffentlicht.
            $table->string('article_url', 500)->nullable();
            $table->timestamp('article_verified_at')->nullable();
        });

        // Bestehende contact_person best-effort in Vor-/Nachname splitten.
        foreach (DB::table('partners')->whereNotNull('contact_person')->get(['id', 'contact_person']) as $p) {
            $parts = preg_split('/\s+/', trim((string) $p->contact_person), 2) ?: [];
            DB::table('partners')->where('id', $p->id)->update([
                'first_name' => $parts[0] ?? null,
                'last_name' => $parts[1] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'salutation', 'first_name', 'last_name',
                'account_holder', 'bic', 'article_url', 'article_verified_at',
            ]);
        });
    }
};
