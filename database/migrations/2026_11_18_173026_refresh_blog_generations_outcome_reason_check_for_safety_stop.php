<?php

declare(strict_types=1);

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blog : rafraichit la contrainte CHECK de `blog_generations.outcome_reason` d'apres l'enum courant, pour la
 * raison `safety_stop` (une generation arretee parce que son cout allait depasser le double du haut annonce,
 * decision de Micha du 18/11). MySQL uniquement, meme motif que
 * 2026_11_17_203200_refresh_blog_generations_outcome_reason_check_for_forecast.
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
