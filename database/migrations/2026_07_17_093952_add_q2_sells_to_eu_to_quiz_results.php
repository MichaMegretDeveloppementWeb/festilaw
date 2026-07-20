<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Q2 du quiz public : un booleen Oui/Non. La colonne q2_eu_countries (JSON) reste reservee au
 * tunnel (liste de pays).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_results', function (Blueprint $table): void {
            $table->boolean('q2_sells_to_eu')->nullable()->after('q1_based_outside_eu');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_results', function (Blueprint $table): void {
            $table->dropColumn('q2_sells_to_eu');
        });
    }
};
