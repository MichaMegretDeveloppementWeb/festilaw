<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesure d'audience : la reference de la visite du client, prise au clic sur « payer » puis a son retour reussi
 * (la derniere l'emporte). Le paiement se compte a l'encaissement confirme, souvent par le webhook de Stripe, qui
 * n'a pas le visiteur : cette reference le range dans la visite ou le client a paye (decision de Micha du 20/11,
 * Falcon Analytics 599edd0). Chaine scellee par la cle de l'application, d'ou du texte. Videe des que le paiement
 * est compte, echoue ou expire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->text('analytics_reference')->nullable()->after('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('analytics_reference');
        });
    }
};
