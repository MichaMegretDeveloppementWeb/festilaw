<?php

declare(strict_types=1);

use App\Enums\Blog\Generation\BlogGenerationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rafraichit la contrainte CHECK sur `blog_generations.type` d'apres l'enum BlogGenerationType courant : la
 * valeur `image` (image principale trouvee depuis un article, demande de Micha du 24/11/2026) a ete ajoutee
 * apres 2026_11_14_100003_add_blog_generation_enum_check_constraints, dont la contrainte figeait l'ancienne
 * liste. MySQL et MariaDB uniquement (SQLite ne supporte pas ces contraintes). Meme motif que
 * 2026_07_21_122707_refresh_payments_type_check_constraint.
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
            static fn (BlogGenerationType $case): string => "'".$case->value."'",
            BlogGenerationType::cases(),
        ));

        // Retrait tolerant : la contrainte peut ne pas exister selon l'historique de la base.
        try {
            DB::statement('ALTER TABLE `blog_generations` DROP CONSTRAINT `blog_generations_type_check`');
        } catch (Throwable) {
        }

        DB::statement("ALTER TABLE `blog_generations` ADD CONSTRAINT `blog_generations_type_check` CHECK (`type` IN ({$values}))");
    }
};
