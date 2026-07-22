<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Retro-remplit service_year sur les paiements crees avant l'ajout de la colonne (nullable). Sans cela,
 * RenewalService::paidThroughYear (qui derive l'annee couverte de service_year, source de verite) ignore
 * ces paiements reussis : le dossier n'est jamais signale au renouvellement et n'affiche aucune date de
 * renouvellement, alors qu'il est bel et bien actif. On repart de l'annee du paiement (paid_at, sinon
 * created_at), coherente avec l'annee 1 (service_year = annee de signature ~ annee de paiement).
 *
 * Migration de DONNEES (prefixe data_) : transaction + requetes DB::table (jamais d'Eloquent, qui
 * dependrait d'un modele susceptible d'evoluer). Idempotente (whereNull) : rejouable sans effet.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('payments')
                ->whereNull('service_year')
                ->orderBy('id')
                ->chunkById(200, function (iterable $payments): void {
                    foreach ($payments as $payment) {
                        $timestamp = $payment->paid_at ?? $payment->created_at;

                        if ($timestamp !== null) {
                            DB::table('payments')
                                ->where('id', $payment->id)
                                ->update(['service_year' => Carbon::parse($timestamp)->year]);
                        }
                    }
                });
        });
    }

    public function down(): void
    {
        // Backfill de donnees : rien a defaire (la colonne est retiree par sa propre migration).
    }
};
