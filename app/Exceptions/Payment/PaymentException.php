<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use App\Exceptions\BaseAppException;
use Throwable;

final class PaymentException extends BaseAppException
{
    private const USER_MESSAGE = 'Online payment is temporarily unavailable. Please try again later or contact us.';

    public static function providerNotEnabled(string $provider): self
    {
        return new self(
            technicalMessage: "Payment provider [{$provider}] is not enabled (see config/payment.php).",
            userMessage: 'This payment method is unavailable. Please choose another one.',
        );
    }

    public static function providerNotConfigured(string $provider): self
    {
        return new self(
            technicalMessage: "Payment provider [{$provider}] is enabled but not configured (missing credentials).",
            userMessage: self::USER_MESSAGE,
        );
    }

    public static function apiRequestFailed(string $operation, ?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: "Payment provider API request failed during [{$operation}].",
            userMessage: self::USER_MESSAGE,
            previous: $previous,
        );
    }

    public static function webhookSignatureInvalid(): self
    {
        return new self(
            technicalMessage: 'Payment webhook rejected: signature mismatch.',
            userMessage: self::USER_MESSAGE,
        );
    }
}
