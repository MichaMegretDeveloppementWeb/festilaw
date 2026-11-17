<?php

declare(strict_types=1);

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blog, SC6 : rafraichit la contrainte CHECK de `blog_generations.outcome_reason` d'apres l'enum courant.
 * La raison `insufficient_sources` (aucune source officielle ou professionnelle apres le test des liens) a
 * ete ajoutee apres 2026_11_14_100003, dont la contrainte figeait l'ancienne liste. MySQL uniquement, meme
 * motif que 2026_07_21_145352_refresh_contracts_signature_status_check_constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->syncConstraint();
    }

    public function down(): void
    {
        // La contrainte reflete l'enum courant dans les deux sens : rien a annuler de destructeur.
        $this->syncConstraint();
    }

    private function syncConstraint(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $values = implode(', ', array_map(
            static fn (BlogGenerationOutcomeReason $case): string => "'".$case->value."'",
            BlogGenerationOutcomeReason::cases(),
        ));

        // Suppression tolerante : la contrainte peut ne pas exister selon l'historique de la base.
        try {
            DB::statement('ALTER TABLE `blog_generations` DROP CONSTRAINT `blog_generations_outcome_reason_check`');
        } catch (Throwable) {
        }

        DB::statement("ALTER TABLE `blog_generations` ADD CONSTRAINT `blog_generations_outcome_reason_check` CHECK (`outcome_reason` IN ({$values}))");
    }
};
