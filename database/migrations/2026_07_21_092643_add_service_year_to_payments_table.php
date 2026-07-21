<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annee de service couverte par un paiement d'abonnement (annee 1 = annee de signature au prorata ;
 * renouvellements = annee cible au plein tarif). Source de verite du suivi des renouvellements :
 * "paye jusqu'a" = max(service_year) des paiements reussis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('service_year')->nullable()->after('amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('service_year');
        });
    }
};
