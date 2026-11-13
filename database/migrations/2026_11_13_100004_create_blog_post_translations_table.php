<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : textes d'un article, une ligne par langue, creee des l'enregistrement meme si la langue
 * n'est pas encore redigee (d'ou les champs nullables). `content` est le document structure de
 * l'editeur (source), `content_html` son rendu, produit par le serveur et servi tel quel au public.
 * Un slug vide est NULL : l'unicite (langue, slug) ne s'applique qu'aux slugs renseignes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title')->nullable();
            $table->string('slug', 100)->nullable();
            $table->text('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->longText('content_html')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamps();

            $table->unique(['blog_post_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_translations');
    }
};
