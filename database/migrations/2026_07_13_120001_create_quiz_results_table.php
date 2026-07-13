<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_results', function (Blueprint $table) {
            $table->id();
            // Rattachement facultatif : le quiz peut etre anonyme (CDC 6).
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('q1_based_outside_eu');
            $table->json('q2_eu_countries')->nullable();
            $table->boolean('q3_sells_restricted');
            $table->string('outcome'); // App\Enums\Quiz\QuizOutcome
            $table->string('locale', 5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_results');
    }
};
