<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `resume_token` est la cle de lookup la plus frequente (binding {dossier}, reprise, telechargements).
 * On l'indexe et on garantit son unicite. Colonne nullable : plusieurs NULL restent autorises.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->unique('resume_token');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropUnique(['resume_token']);
        });
    }
};
