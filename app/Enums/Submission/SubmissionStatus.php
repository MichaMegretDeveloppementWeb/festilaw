<?php

declare(strict_types=1);

namespace App\Enums\Submission;

enum SubmissionStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case AwaitingDocuments = 'awaiting_documents';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
