<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Alerte technique (francophone) : plusieurs generations d'articles viennent d'echouer d'affilee. Envoyee
 * au prestataire technique (FESTILAW_TECH_ALERT_EMAIL) une fois par serie d'echecs. Elle dit a quelle etape
 * chaque generation a echoue et y mene ; la cause technique n'est ecrite qu'au journal du serveur.
 *
 * @phpstan-type FailedGenerationRow array{subject: string, outcome: string, url: string}
 */
final class BlogGenerationFailuresAlert extends Mailable
{
    /**
     * @param  list<FailedGenerationRow>  $rows  les generations de la serie, la plus recente d'abord
     * @param  string  $generationsUrl  le journal des generations du back-office
     */
    public function __construct(
        public int $failures,
        public array $rows,
        public string $generationsUrl,
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
