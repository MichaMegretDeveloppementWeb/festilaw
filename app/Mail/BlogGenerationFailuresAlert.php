<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Alerte technique (francophone) : plusieurs generations d'articles viennent d'echouer d'affilee. Envoyee
 * au prestataire technique (FESTILAW_TECH_ALERT_EMAIL) une fois par serie d'echecs.
 */
final class BlogGenerationFailuresAlert extends Mailable
{
    public function __construct(
        public int $failures,
        public ?string $lastError,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Festilaw · générations d\'articles en échec (:count d\'affilée)', ['count' => $this->failures]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.blog-generation-failures-alert');
    }
}
