<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresse officielle de Personne Responsable UE delivree au client (saisie par le back-office
 * quand le dossier est finalise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->text('eu_rp_address')->nullable()->after('meta');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn('eu_rp_address');
        });
    }
};
