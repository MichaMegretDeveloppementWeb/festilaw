<?php

use App\Enums\Payment\PaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rafraichit la contrainte CHECK sur `payments.type` d'apres l'enum PaymentType courant : la valeur
 * `annual_renewal` (renouvellements) a ete ajoutee apres 2026_07_16_add_enum_check_constraints, dont
 * la contrainte figeait l'ancienne liste. MySQL uniquement (SQLite ne supporte pas ces contraintes).
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
