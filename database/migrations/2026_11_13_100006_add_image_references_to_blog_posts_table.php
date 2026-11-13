<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : image principale et image de partage d'un article. Migration separee car blog_images
 * reference blog_posts et inversement : les deux tables doivent exister avant de poser ces cles.
 * L'image de partage est facultative (a defaut, celle de l'image principale est utilisee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreignId('featured_image_id')->nullable()->after('blog_category_id')
                ->constrained('blog_images')->nullOnDelete();
            $table->foreignId('share_image_id')->nullable()->after('featured_image_id')
                ->constrained('blog_images')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('share_image_id');
            $table->dropConstrainedForeignId('featured_image_id');
        });
    }
};
