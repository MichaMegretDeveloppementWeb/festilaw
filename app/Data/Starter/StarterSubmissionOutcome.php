<?php

declare(strict_types=1);

namespace App\Data\Starter;

use App\Models\Submission;

/**
 * Outcome of opening a STARTER file: the dossier to work with, and whether it was freshly created
 * (redirect the visitor into it) or an existing open dossier was found for this email (the resume link
 * was re-sent by email instead, and the visitor is NOT dropped into it · the resume token is a
 * capability URL, so an email match alone must not grant access).
 */
final readonly class StarterSubmissionOutcome
{
    public function __construct(
        public Submission $submission,
        public bool $isNew,
    ) {}
}
