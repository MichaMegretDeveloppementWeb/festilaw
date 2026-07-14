<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use App\Exceptions\BaseAppException;

final class PaymentException extends BaseAppException
{
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
            userMessage: 'Online payment is temporarily unavailable. Please try again later or contact us.',
        );
    }
}
