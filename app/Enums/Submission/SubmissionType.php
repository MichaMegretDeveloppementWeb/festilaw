<?php

declare(strict_types=1);

namespace App\Enums\Submission;

enum SubmissionType: string
{
    case Contact = 'contact';
    case Starter = 'starter';
    case Pro = 'pro';
    case Scale = 'scale';
}
