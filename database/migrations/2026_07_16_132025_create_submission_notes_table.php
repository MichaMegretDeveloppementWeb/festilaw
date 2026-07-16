<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes internes de l'equipe sur un dossier (back-office). Suivi du traitement manuel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_notes');
    }
};
