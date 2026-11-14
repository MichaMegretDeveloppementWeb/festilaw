<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Message interne (francophone) : la generation d'articles est suspendue pour une raison d'argent. Soit le
 * plafond de depense regle au back-office est atteint, soit le compte du fournisseur d'IA n'a plus de credit
 * (`$creditExhausted`). Envoye au plus une fois par mois pour chacune des deux causes.
 */
final class BlogSpendLimitReached extends Mailable
{
    public function __construct(
        public bool $creditExhausted,
        public string $spent,
        public string $cap,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->creditExhausted
            ? __('Festilaw · crédit d\'IA épuisé, génération d\'articles suspendue')
            : __('Festilaw · plafond de dépense atteint, génération d\'articles suspendue'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.blog-spend-limit-reached');
    }
}
