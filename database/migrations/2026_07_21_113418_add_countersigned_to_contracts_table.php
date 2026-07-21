<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrat contresigne par Festilaw (contre-signature manuelle, hors SignWell), depose par l'admin
 * depuis le back-office. Fichier sur le disque prive (hors webroot), visible par l'admin et par le
 * client dans son espace dossier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('countersigned_file_path')->nullable()->after('signed_at');
            $table->timestamp('countersigned_at')->nullable()->after('countersigned_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['countersigned_file_path', 'countersigned_at']);
        });
    }
};
