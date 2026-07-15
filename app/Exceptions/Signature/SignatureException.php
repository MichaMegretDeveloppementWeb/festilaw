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

    public static function tokenExchangeFailed(?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: 'Zoho Sign OAuth token exchange failed (check client id/secret, refresh token and datacenter).',
            userMessage: self::USER_MESSAGE,
            previous: $previous,
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

    public static function templateSignerMissing(string $templateId): self
    {
        return new self(
            technicalMessage: "Zoho Sign template [{$templateId}] has no SIGN recipient action to bind the signer to.",
            userMessage: self::USER_MESSAGE,
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
