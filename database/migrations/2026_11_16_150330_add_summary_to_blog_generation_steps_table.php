<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : compte rendu d'une etape de generation. Une ou deux phrases en francais, ecrites par l'etape pour la
 * personne qui relit (pourquoi ce sujet, ce qui a ete consulte, comment l'article a ete traite). Colonne a
 * part, et non une cle de `output` : l'ecran d'une generation l'affiche sans charger ce que l'etape a
 * produit, qui peut etre le texte entier d'un article.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_generation_steps', function (Blueprint $table): void {
            $table->text('summary')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('blog_generation_steps', function (Blueprint $table): void {
            $table->dropColumn('summary');
        });
    }
};
