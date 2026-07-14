<?php

declare(strict_types=1);

namespace App\Exceptions\Signature;

use App\Exceptions\BaseAppException;

final class SignatureException extends BaseAppException
{
    public static function providerNotConfigured(string $provider): self
    {
        return new self(
            technicalMessage: "Signature provider [{$provider}] is not configured (missing credentials).",
            userMessage: 'Online signature is temporarily unavailable. Please try again later or contact us.',
        );
    }
}
