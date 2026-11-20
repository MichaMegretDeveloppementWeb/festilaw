<?php

declare(strict_types=1);

namespace App\Data\System;

use App\Enums\System\ErrorReportOutcome;

/** Le rapport d'erreurs du matin : ce qu'il contenait, et s'il est parti. */
final readonly class DailyErrorReportResult
{
    public function __construct(
        public ErrorReportOutcome $outcome,
        public ErrorLogSummary $summary,
    ) {}
}
