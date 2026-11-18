<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : le cout annonce d'une generation, bas et haut, fige a son lancement (decision de Micha du 18/11). Le
 * plafond par article disparait : la generation garde ce qu'elle a annonce, l'ecran le montre a cote du cout
 * reel, et elle ne s'arrete par securite qu'au double du haut annonce. Nul pour une generation anterieure, qui
 * le calcule au debut de son traitement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_generations', function (Blueprint $table): void {
            $table->unsignedBigInteger('forecast_low_microusd')->nullable()->after('cost_microusd');
            $table->unsignedBigInteger('forecast_high_microusd')->nullable()->after('forecast_low_microusd');
        });
    }

    public function down(): void
    {
        Schema::table('blog_generations', function (Blueprint $table): void {
            $table->dropColumn(['forecast_low_microusd', 'forecast_high_microusd']);
        });
    }
};
