<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contraintes d'unicite : la ceinture de securite BDD des invariants deja tenus par le code.
 *  - une seule piece par type et par dossier ;
 *  - un seul rendez-vous par dossier ;
 *  - une reference prestataire non nulle unique par prestataire (anti double-session sur retry / course
 *    webhook). Les NULL restent distincts dans un index unique : plusieurs paiements en attente (sans
 *    reference) coexistent sans probleme.
 *
 * Les index uniques sont portables MySQL + SQLite (contrairement aux CHECK d'enum, reserves a MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_documents', function (Blueprint $table): void {
            $table->unique(['submission_id', 'type'], 'uploaded_documents_submission_type_unique');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->unique('submission_id', 'appointments_submission_id_unique');
        });

        Schema::table('payments', function (Blueprint $table): void {
            // L'ancien index non unique couvrait deja (provider, provider_reference) pour le lookup webhook :
            // on le remplace par un index UNIQUE sur les memes colonnes (le lookup reste indexe).
            $table->dropIndex('payments_provider_reference_idx');
            $table->unique(['provider', 'provider_reference'], 'payments_provider_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_documents', function (Blueprint $table): void {
            $table->dropUnique('uploaded_documents_submission_type_unique');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropUnique('appointments_submission_id_unique');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_provider_reference_unique');
            $table->index(['provider', 'provider_reference'], 'payments_provider_reference_idx');
        });
    }
};
