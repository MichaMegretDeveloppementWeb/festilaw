<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : une generation qui attend un nouvel essai apres une panne passagere du fournisseur d'IA (429, 5xx,
 * delai depasse) n'est pas reprise avant `not_before` (controle du 20/11). Le worker ne dort plus en tenant le
 * verrou : il rend la generation, et les autres passent en attendant. Nul : reprise des que possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_generations', function (Blueprint $table): void {
            $table->timestamp('not_before')->nullable()->after('locked_until');
        });
    }

    public function down(): void
    {
        Schema::table('blog_generations', function (Blueprint $table): void {
            $table->dropColumn('not_before');
        });
    }
};
