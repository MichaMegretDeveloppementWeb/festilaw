<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Submission;
use App\Models\SubmissionNote;

/**
 * Ajoute une note interne (back-office) a un dossier.
 */
final readonly class AddSubmissionNoteAction
{
    public function execute(Submission $submission, string $body, ?int $authorId): SubmissionNote
    {
        return $submission->notes()->create([
            'author_id' => $authorId,
            'body' => $body,
        ]);
    }
}
