<?php

use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;

/**
 * Retro-remplit service_year sur les paiements crees avant l'ajout de la colonne (nullable). Sans cela,
 * RenewalService::paidThroughYear (qui derive l'annee couverte de service_year, source de verite) ignore
 * ces paiements reussis : le dossier n'est jamais signale au renouvellement et n'affiche aucune date de
 * renouvellement, alors qu'il est bel et bien actif. On repart de l'annee du paiement (paid_at, sinon
 * created_at), coherente avec l'annee 1 (service_year = annee de signature ~ annee de paiement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Payment::query()
            ->whereNull('service_year')
            ->chunkById(200, function (Collection $payments): void {
                $payments->each(function (Payment $payment): void {
                    $year = ($payment->paid_at ?? $payment->created_at)?->year;

                    if ($year !== null) {
                        $payment->updateQuietly(['service_year' => $year]);
                    }
                });
            });
    }

    public function down(): void
    {
        // Backfill de donnees : rien a defaire (la colonne est retiree par sa propre migration).
    }
};
