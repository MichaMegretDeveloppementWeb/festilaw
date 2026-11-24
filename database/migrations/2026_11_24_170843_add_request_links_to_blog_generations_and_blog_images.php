<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : une image ou une traduction demandee depuis un article ne se perd plus (retour de Micha du 24/11/2026,
 * une image payee en production n'etait plus retrouvable).
 *
 * `blog_generations.source_blog_post_id` : l'article depuis lequel la demande a ete faite. Ce n'est pas
 * `blog_post_id`, l'article produit par une generation. Nul a la suppression de l'article.
 * `blog_generations.delivered_at` : quand le formulaire a recu le resultat de la demande ; nul, il le recevra au
 * retour sur l'article.
 * `blog_images.generation_id` : la generation qui a obtenu cette image comme image principale (nul pour une image
 * deposee a la main ou une image du texte). Une telle image reste a l'article tant qu'il existe, meme remplacee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_generations', function (Blueprint $table): void {
            $table->foreignId('source_blog_post_id')->nullable()->after('blog_post_id')->constrained('blog_posts')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable()->after('finished_at');
        });

        Schema::table('blog_images', function (Blueprint $table): void {
            $table->foreignId('generation_id')->nullable()->after('blog_post_id')->constrained('blog_generations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_images', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('generation_id');
        });

        Schema::table('blog_generations', function (Blueprint $table): void {
            $table->dropColumn('delivered_at');
            $table->dropConstrainedForeignId('source_blog_post_id');
        });
    }
};
