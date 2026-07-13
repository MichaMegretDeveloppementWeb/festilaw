<?php

declare(strict_types=1);

namespace App\Enums\Payment;

enum PaymentType: string
{
    case StarterSubscription = 'starter_subscription';
    case ScaleAudit = 'scale_audit';
}
