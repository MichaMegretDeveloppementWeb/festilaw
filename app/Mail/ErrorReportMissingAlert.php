<?php

declare(strict_types=1);

namespace App\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Alerte technique (francophone) : le rapport d'erreurs du matin n'a rien mene a bien depuis $lastDoneAt, alors
 * que les taches automatiques tournent. Envoyee au prestataire technique (FESTILAW_TECH_ALERT_EMAIL), une seule
 * fois par arret.
 */
final class ErrorReportMissingAlert extends InternalMail
{
    public function __construct(public CarbonImmutable $lastDoneAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Festilaw · rapport d\'erreurs du matin à l\'arrêt'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.error-report-missing-alert');
    }
}
