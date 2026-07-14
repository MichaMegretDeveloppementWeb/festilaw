<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploaded_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // App\Enums\Document\DocumentType
            $table->string('file_path'); // disque prive, hors webroot
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_documents');
    }
};
