<?php

namespace Database\Factories;

use App\Enums\Document\DocumentType;
use App\Models\Submission;
use App\Models\UploadedDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UploadedDocument>
 */
class UploadedDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory()->starter(),
            'type' => DocumentType::TurnoverProof,
            'file_path' => 'private/documents/'.fake()->uuid().'.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1000, 500000),
            'uploaded_at' => now(),
        ];
    }
}
