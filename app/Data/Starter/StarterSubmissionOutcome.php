<?php

declare(strict_types=1);

namespace App\Data\Starter;

use App\Models\Submission;

/**
 * Outcome of opening a STARTER file. Three cases, none of which drop the visitor straight into an
 * existing dossier (the resume token is a capability URL · an email match alone must not grant access):
 *  - isNew        : a fresh dossier was created · the visitor is redirected into it.
 *  - isActive     : the email already has an ACTIVE (paid) subscription · its link was re-sent by email.
 *  - neither      : an unfinished dossier is in progress · its resume link was re-sent by email.
 */
final readonly class StarterSubmissionOutcome
{
    public function __construct(
        public Submission $submission,
        public bool $isNew,
        public bool $isActive = false,
    ) {}
}
