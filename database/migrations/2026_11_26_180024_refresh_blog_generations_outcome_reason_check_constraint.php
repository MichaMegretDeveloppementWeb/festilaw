<?php

declare(strict_types=1);

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rafraichit la contrainte CHECK sur `blog_generations.outcome_reason` d'apres l'enum BlogGenerationOutcomeReason
 * courant : la valeur `topic_out_of_scope` (sujet impose hors du domaine du blog, essai du 26/11/2026) a ete ajoutee
 * apres 2026_11_14_100003_add_blog_generation_enum_check_constraints, dont la contrainte figeait la liste du moment.
 * Une base creee avant le 20/11 y gagne aussi les raisons ajoutees ce jour-la (arrets de securite, cle refusee,
 * modele retire). MySQL et MariaDB uniquement (SQLite ne supporte pas ces contraintes). Meme motif que
 * 2026_11_24_143745_refresh_blog_generations_type_check_constraint.
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

        // Retrait tolerant : la contrainte peut ne pas exister selon l'historique de la base.
        try {
            DB::statement('ALTER TABLE `blog_generations` DROP CONSTRAINT `blog_generations_outcome_reason_check`');
        } catch (Throwable) {
        }

        DB::statement("ALTER TABLE `blog_generations` ADD CONSTRAINT `blog_generations_outcome_reason_check` CHECK (`outcome_reason` IN ({$values}))");
    }
};
