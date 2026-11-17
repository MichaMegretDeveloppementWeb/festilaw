<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Message interne (francophone) : la generation automatique n'a pas lance son lot, parce qu'avec les reglages
 * en vigueur un article peut couter plus que le plafond par article. Envoye quand le blocage commence, pas a
 * chaque passage de la tache planifiee.
 */
final class BlogForecastAboveCap extends Mailable
{
    public function __construct(
        public string $low,
        public string $high,
        public string $cap,
        public string $settingsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Festilaw · génération automatique retenue, coût estimé au-dessus du plafond par article'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.blog-forecast-above-cap');
    }
}
