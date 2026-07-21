<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Recap interne (francophone) envoye a Festilaw : la liste des dossiers a renouveler (ou en retard,
 * selon $overdue), pour un suivi simple des renouvellements depuis la boite mail. Un seul message
 * groupe par execution, plutot qu'un mail par client.
 *
 * @phpstan-type RenewalRow array{company: string, pack: string, year: int, email: string, url: string}
 */
final class AdminRenewalDigest extends Mailable
{
    /** @param  list<RenewalRow>  $rows */
    public function __construct(
        public array $rows,
        public bool $overdue = false,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->overdue
            ? __('Festilaw · renouvellements en retard')
            : __('Festilaw · renouvellements à venir');

        return new Envelope(subject: $subject.' ('.count($this->rows).')');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-renewal-digest');
    }
}
