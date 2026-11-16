<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : qui a arrete une generation. L'ecran d'une generation arretee doit dire par qui, quand et apres
 * quelle etape, et non le seul mot « arretee ». La date est deja dans `cancel_requested_at` ; cette colonne
 * porte la personne. Nulle quand le compte a ete supprime depuis, ou pour une generation jamais arretee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_generations', function (Blueprint $table): void {
            $table->foreignId('cancel_requested_by')->nullable()->after('cancel_requested_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_generations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancel_requested_by');
        });
    }
};
