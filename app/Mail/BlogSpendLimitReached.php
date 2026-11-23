<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Message interne (francophone) : le compte du fournisseur d'IA n'a plus de credit, ou la limite posee sur la
 * cle est atteinte. La generation d'articles est arretee jusqu'au rechargement. Envoye au plus une fois par
 * mois. Le plafond du mois regle au back-office a son propre message (BlogMonthlySpendWarning), car il ne
 * bloque rien.
 */
final class BlogSpendLimitReached extends InternalMail
{
    public function __construct(
        public string $spent,
        public string $cap,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Festilaw · crédit d\'IA épuisé, génération d\'articles suspendue'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.blog-spend-limit-reached');
    }
}
