<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base of every business exception: carries a technical message (English, for logs)
 * and a user-facing message (project language, safe to display).
 */
abstract class BaseAppException extends RuntimeException
{
    public function __construct(
        string $technicalMessage,
        protected readonly string $userMessage,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($technicalMessage, $code, $previous);
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
}
