<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : anciens slugs d'articles. Une ligne rattachee a un article = redirection 301 vers son
 * slug actuel. Une ligne sans article (`blog_post_id` nul, pose par la base a la suppression de
 * l'article) = l'article n'existe plus : reponse 410.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_retired_slugs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 5);
            $table->string('slug', 100);
            $table->timestamps();

            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_retired_slugs');
    }
};
