<?php

declare(strict_types=1);

namespace App\Exceptions\Signature;

use App\Exceptions\BaseAppException;
use Throwable;

final class SignatureException extends BaseAppException
{
    private const USER_MESSAGE = 'Online signature is temporarily unavailable. Please try again in a moment or contact us.';

    public static function providerNotConfigured(string $provider): self
    {
        return new self(
            technicalMessage: "Signature provider [{$provider}] is not configured (missing credentials).",
            userMessage: self::USER_MESSAGE,
        );
    }

    public static function apiRequestFailed(string $operation, ?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: "Signature provider API request failed during [{$operation}].",
            userMessage: self::USER_MESSAGE,
            previous: $previous,
        );
    }

    public static function webhookSignatureInvalid(): self
    {
        return new self(
            technicalMessage: 'Signature webhook rejected: HMAC signature mismatch.',
            userMessage: self::USER_MESSAGE,
        );
    }
}
