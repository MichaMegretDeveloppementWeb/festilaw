<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changement de pack avant paiement : le dossier en cours est annule et remplace par un nouveau dossier
 * au pack choisi. replaced_by_id relie l'ancien dossier a son remplacant (trace au back-office, purge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->foreignId('replaced_by_id')->nullable()->after('eu_rp_address')
                ->constrained('submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replaced_by_id');
        });
    }
};
