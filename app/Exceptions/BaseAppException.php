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
    /**
     * Un refus du a l'utilisation (fichier au mauvais format, adresse prise, article pas pret), que l'ecran
     * explique et que la personne corrige elle-meme. Journalise en warning, il reste hors du rapport d'erreurs
     * du matin, reserve aux erreurs du code et des services (decision de Micha du 23/11). Par defaut, non : une
     * exception typee qui enveloppe une panne reste en error.
     */
    private bool $refusal = false;

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

    /** Marque l'exception comme un refus du a l'utilisation (voir $refusal). */
    public function asRefusal(): static
    {
        $this->refusal = true;

        return $this;
    }

    public function isRefusal(): bool
    {
        return $this->refusal;
    }

    /** Le niveau de journal : warning pour un refus d'utilisation, error pour tout le reste. */
    public function logLevel(): string
    {
        return $this->refusal ? 'warning' : 'error';
    }
}
