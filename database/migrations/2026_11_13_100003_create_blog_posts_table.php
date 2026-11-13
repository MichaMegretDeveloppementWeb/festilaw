<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : article, partie commune aux trois langues (les textes sont dans blog_post_translations).
 * La categorie est facultative pour un brouillon et obligatoire pour publier ; une categorie qui
 * contient des articles ne peut pas etre supprimee (restrict). Les images principale et de partage
 * sont ajoutees par une migration ulterieure, apres la creation de blog_images.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft'); // App\Enums\Blog\BlogPostStatus
            $table->string('origin', 20)->default('manual'); // App\Enums\Blog\BlogPostOrigin
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable(); // premiere publication, conservee ensuite
            $table->timestamp('content_updated_at')->nullable(); // modification de fond apres publication
            $table->json('sources')->nullable(); // liste {label, url}
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
