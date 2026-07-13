<?php

declare(strict_types=1);

namespace App\Enums\Contract;

enum SignatureStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Declined = 'declined';
}
