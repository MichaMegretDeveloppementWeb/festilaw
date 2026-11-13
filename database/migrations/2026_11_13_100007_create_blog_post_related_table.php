<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : articles associes choisis a la main, dans l'ordre voulu. Sans choix, la page publique
 * propose les plus recents de la meme categorie (rien a stocker pour ce cas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_related', function (Blueprint $table): void {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            $table->primary(['blog_post_id', 'related_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_related');
    }
};
