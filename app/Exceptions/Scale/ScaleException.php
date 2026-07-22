<?php

declare(strict_types=1);

namespace App\Exceptions\Scale;

use App\Exceptions\BaseAppException;

final class ScaleException extends BaseAppException
{
    public static function auditAlreadyPaid(int $submissionId): self
    {
        return new self(
            technicalMessage: "SCALE submission [{$submissionId}] already has a paid audit: refusing to start a second audit payment.",
            userMessage: 'Your audit is already paid · you can book your consultation.',
        );
    }

    public static function auditNotPaid(int $submissionId): self
    {
        return new self(
            technicalMessage: "SCALE submission [{$submissionId}] cannot book a consultation before the audit is paid.",
            userMessage: 'Please pay the €75 audit fee before booking your consultation.',
        );
    }
}
