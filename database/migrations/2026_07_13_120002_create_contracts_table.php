<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            // 1 contrat par demande STARTER.
            $table->foreignId('submission_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('filled_fields')->nullable();
            $table->string('signature_status'); // App\Enums\Contract\SignatureStatus
            // Provider de signature (config, un seul actif a la fois) + reference cote provider.
            $table->string('signature_provider')->nullable();
            $table->string('signature_provider_reference')->nullable();
            $table->string('signed_file_path')->nullable(); // disque prive, hors webroot
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
