<?php

use App\Enums\Contract\SignatureStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rafraichit la contrainte CHECK sur `contracts.signature_status` d'apres l'enum SignatureStatus courant :
 * la valeur `expired` (document non signe expire) a ete ajoutee apres 2026_07_16_add_enum_check_constraints,
 * dont la contrainte figeait l'ancienne liste. MySQL uniquement. Meme motif que les refresh de paiement.
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
            static fn (SignatureStatus $case): string => "'".$case->value."'",
            SignatureStatus::cases(),
        ));

        // Drop tolerant : la contrainte peut ne pas exister selon l'historique de la base.
        try {
            DB::statement('ALTER TABLE `contracts` DROP CONSTRAINT `contracts_signature_status_check`');
        } catch (Throwable) {
        }

        DB::statement("ALTER TABLE `contracts` ADD CONSTRAINT `contracts_signature_status_check` CHECK (`signature_status` IN ({$values}))");
    }
};
