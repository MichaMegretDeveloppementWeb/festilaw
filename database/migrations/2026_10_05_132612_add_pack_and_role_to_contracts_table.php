<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Changement de pack apres paiement (SC12) : un dossier peut avoir plusieurs mandats au fil du temps (le
 * mandat signe d'un ancien pack reste conserve). Le contrat porte donc son pack, et son role : en vigueur
 * ("current", un seul par dossier, tenu par le code), en attente d'un changement de pack ("pending"), ou
 * remplace ("superseded", conserve comme trace).
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL : la cle etrangere s'appuie sur l'index unique ; on pose d'abord un index simple.
        Schema::table('contracts', function (Blueprint $table): void {
            $table->index('submission_id', 'contracts_submission_id_idx');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropUnique(['submission_id']);
            $table->string('pack')->nullable()->after('submission_id'); // App\Enums\Submission\SubmissionType
            $table->string('role')->default('current')->after('pack'); // App\Enums\Contract\ContractRole
            $table->timestamp('superseded_at')->nullable()->after('role');
        });

        // Les mandats existants sont ceux du pack actuel de leur dossier.
        DB::statement('UPDATE contracts SET pack = (SELECT type FROM submissions WHERE submissions.id = contracts.submission_id)');
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['pack', 'role', 'superseded_at']);
            $table->unique('submission_id');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropIndex('contracts_submission_id_idx');
        });
    }
};
