<?php

use App\Enums\Payment\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rafraichit la contrainte CHECK sur `payments.status` d'apres l'enum PaymentStatus courant : les valeurs
 * `processing` (paiements asynchrones en cours) et `expired` (session de checkout abandonnee) ont ete
 * ajoutees apres 2026_07_16_add_enum_check_constraints, dont la contrainte figeait l'ancienne liste.
 * MySQL uniquement (SQLite ne supporte pas ces contraintes). Meme motif que le refresh de `type`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->syncConstraint();
    }

    public function down(): void
    {
        // La contrainte reflete l'enum courant dans les deux sens : rien a "annuler" de destructeur.
        $this->syncConstraint();
    }

    private function syncConstraint(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $values = implode(', ', array_map(
            static fn (PaymentStatus $case): string => "'".$case->value."'",
            PaymentStatus::cases(),
        ));

        // Drop tolerant : la contrainte peut ne pas exister selon l'historique de la base.
        try {
            DB::statement('ALTER TABLE `payments` DROP CONSTRAINT `payments_status_check`');
        } catch (Throwable) {
        }

        DB::statement("ALTER TABLE `payments` ADD CONSTRAINT `payments_status_check` CHECK (`status` IN ({$values}))");
    }
};
