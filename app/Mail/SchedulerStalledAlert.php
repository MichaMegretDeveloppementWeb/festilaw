<?php

declare(strict_types=1);

namespace App\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Alerte technique (francophone) : les taches automatiques du site ne tournent plus depuis $lastRunAt.
 * Envoyee au prestataire technique (FESTILAW_TECH_ALERT_EMAIL), au plus toutes les 6 heures.
 */
final class SchedulerStalledAlert extends Mailable
{
    public function __construct(public ?CarbonImmutable $lastRunAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Festilaw · tâches automatiques à l\'arrêt'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.scheduler-stalled-alert');
    }
}
