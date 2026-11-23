<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : une categorie peut etre creee par la generation d'un article, quand aucune categorie existante ne
 * convient a son theme (decision de Micha du 23/11). `generation_id` dit laquelle : supprimer le brouillon de
 * cette generation supprime aussi la categorie si plus aucun article ne l'emploie. Nul : categorie creee a la
 * main, jamais supprimee ainsi. La generation supprimee, la categorie reste et devient comme une autre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_categories', function (Blueprint $table): void {
            $table->foreignId('generation_id')->nullable()->after('position')->constrained('blog_generations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('generation_id');
        });
    }
};
