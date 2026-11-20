<?php

declare(strict_types=1);

namespace App\Enums\System;

/** Ce qu'est devenu le rapport d'erreurs du matin (festilaw:error-report). */
enum ErrorReportOutcome: string
{
    case NothingToReport = 'nothing_to_report';
    case Sent = 'sent';
    case DryRun = 'dry_run';
    case NoRecipient = 'no_recipient';
    case SendFailed = 'send_failed';
}
