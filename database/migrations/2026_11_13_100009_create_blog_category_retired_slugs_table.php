<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : anciens slugs de categories (redirection 301 vers le slug actuel). Ils disparaissent avec
 * la categorie : une categorie supprimee repond 404, sans page "n'existe plus".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_category_retired_slugs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('slug', 100);
            $table->timestamps();

            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_category_retired_slugs');
    }
};
