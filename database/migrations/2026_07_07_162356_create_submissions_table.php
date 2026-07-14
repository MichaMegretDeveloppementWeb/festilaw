<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('type');   // App\Enums\Submission\SubmissionType
            $table->string('status'); // App\Enums\Submission\SubmissionStatus
            $table->string('locale', 5);

            // Contact / entreprise (champs remplis selon le type de soumission)
            $table->string('company_name')->nullable();
            $table->string('company_registration_number')->nullable();
            $table->string('website_url')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->json('eu_sales_countries')->nullable();
            $table->string('product_types')->nullable();
            $table->text('message')->nullable();

            // Reprise du dossier STARTER (lien magique)
            $table->string('resume_token')->nullable();
            $table->timestamp('resume_expires_at')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('type', 'submissions_type_idx');
            $table->index('status', 'submissions_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
