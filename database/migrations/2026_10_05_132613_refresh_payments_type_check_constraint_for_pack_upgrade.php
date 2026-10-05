<?php

use App\Enums\Payment\PaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rafraichit la contrainte CHECK sur `payments.type` d'apres l'enum PaymentType courant : la valeur
 * `pack_upgrade` (passage du Creator au Pro apres paiement, SC12) est nouvelle. MySQL uniquement (SQLite ne
 * supporte pas ces contraintes). Meme principe que 2026_07_21_122707_refresh_payments_type_check_constraint.
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
            static fn (PaymentType $case): string => "'".$case->value."'",
            PaymentType::cases(),
        ));

        // Drop tolerant : la contrainte peut ne pas exister selon l'historique de la base.
        try {
            DB::statement('ALTER TABLE `payments` DROP CONSTRAINT `payments_type_check`');
        } catch (Throwable) {
        }

        DB::statement("ALTER TABLE `payments` ADD CONSTRAINT `payments_type_check` CHECK (`type` IN ({$values}))");
    }
};
