<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog, SC6 : la generation qui a cree un brouillon. Le moteur peut rejouer l'etape d'assemblage (verrou
 * perdu, erreur apres l'enregistrement) : la contrainte d'unicite garantit qu'une generation ne cree jamais
 * deux brouillons, et l'assemblage retrouve celui qu'il a deja cree. Nulle pour un article ecrit a la main.
 * Si la generation est supprimee, l'article reste et perd seulement ce lien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('generation_id')->nullable()->unique()->after('origin')
                ->constrained('blog_generations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generation_id');
        });
    }
};
