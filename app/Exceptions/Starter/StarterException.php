<?php

declare(strict_types=1);

namespace App\Exceptions\Starter;

use App\Exceptions\BaseAppException;

final class StarterException extends BaseAppException
{
    public static function dossierIncomplete(int $submissionId): self
    {
        return new self(
            technicalMessage: "STARTER submission [{$submissionId}] cannot proceed to payment: dossier is incomplete.",
            userMessage: 'Please sign your contract and upload all required documents before paying.',
        );
    }

    public static function contractMissing(int $submissionId): self
    {
        return new self(
            technicalMessage: "STARTER submission [{$submissionId}] has no contract to sign.",
            userMessage: 'Your file could not be found. Please start again or contact us.',
        );
    }
}
