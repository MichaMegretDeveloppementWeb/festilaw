<?php

declare(strict_types=1);

namespace App\Exceptions\Signature;

use App\Exceptions\BaseAppException;
use Throwable;

final class SignatureException extends BaseAppException
{
    public static function providerNotConfigured(string $provider): self
    {
        return new self(
            technicalMessage: "Signature provider [{$provider}] is not configured (missing credentials).",
            userMessage: 'Online signature is temporarily unavailable. Please try again later or contact us.',
        );
    }

    public static function sessionCreationFailed(string $provider, Throwable $previous): self
    {
        return new self(
            technicalMessage: "Failed to create a signing session with provider [{$provider}]: {$previous->getMessage()}.",
            userMessage: 'We could not start the signature process. Please try again or contact us.',
            previous: $previous,
        );
    }
}
