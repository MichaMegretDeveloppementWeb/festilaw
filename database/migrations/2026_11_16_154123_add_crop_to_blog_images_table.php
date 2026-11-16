<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : cadrage d'une image du texte. Une image ajoutee par la fenetre de recadrage garde, a cote de ses
 * variantes, sa source non decoupee ; `crop` est le rectangle garde ({x, y, width, height}, en pixels de
 * cette source). Il sert a rouvrir l'outil de recadrage sur le cadrage en place. Nul pour une image sans
 * source gardee (image principale, image de partage, image anterieure a cette colonne).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_images', function (Blueprint $table): void {
            $table->json('crop')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('blog_images', function (Blueprint $table): void {
            $table->dropColumn('crop');
        });
    }
};
