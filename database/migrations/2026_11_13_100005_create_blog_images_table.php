<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : images. Les fichiers vivent sur le disque public, dans blog/{uuid}/ (un dossier par image).
 * `blog_post_id` nul = image en attente (envoyee, pas encore rattachee a un article enregistre) ou
 * orpheline (article supprime, image retiree du contenu) : la purge planifiee la supprime, fichiers
 * compris. `variants` liste les fichiers produits ; `alt` porte une description par langue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_images', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('blog_post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('basename', 80); // nom lisible genere par le serveur, fige a l'envoi
            $table->json('variants'); // {widths: [480, ...], share: bool}
            $table->unsignedInteger('width'); // dimensions de la plus grande variante, apres orientation
            $table->unsignedInteger('height');
            $table->json('alt')->nullable(); // {en: "...", fr: "...", es: "..."}
            $table->string('credit_source', 20)->default('upload'); // App\Enums\Blog\BlogImageCreditSource
            $table->string('credit_author')->nullable();
            $table->string('credit_url', 500)->nullable();
            $table->string('credit_license')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_images');
    }
};
