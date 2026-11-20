<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Alerte technique (francophone) : plusieurs generations d'articles viennent de finir sans brouillon
 * d'affilee (echec, arret de securite, doublon, aucun sujet). Envoyee au prestataire technique
 * (FESTILAW_TECH_ALERT_EMAIL) une fois par serie. Elle dit ce qui a arrete chaque generation et y mene ; la
 * cause technique n'est ecrite qu'au journal du serveur, que le rapport d'erreurs du matin reprend.
 *
 * @phpstan-type FailedGenerationRow array{subject: string, outcome: string, url: string}
 */
final class BlogGenerationFailuresAlert extends Mailable
{
    /**
     * @param  int  $failures  le nombre de generations de la serie
     * @param  list<FailedGenerationRow>  $rows  les dernieres generations de la serie, la plus recente d'abord
     * @param  string  $generationsUrl  le journal des generations du back-office
     */
    public function __construct(
        public int $failures,
        public array $rows,
        public string $generationsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Générations d\'articles sans brouillon sur festilaw.com (:count d\'affilée)', ['count' => $this->failures]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.blog-generation-failures-alert');
    }
}
