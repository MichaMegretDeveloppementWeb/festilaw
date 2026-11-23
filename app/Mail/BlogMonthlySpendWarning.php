<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Message interne (francophone) : la depense d'IA du mois approche de son plafond (`$over` faux), ou l'a
 * depasse. Ce plafond ne bloque rien (decision de Micha du 17/11) : le message le dit, pour qu'on ne croie pas
 * la generation arretee. Envoye au plus une fois par mois pour chacun des deux seuils.
 */
final class BlogMonthlySpendWarning extends InternalMail
{
    public function __construct(
        public bool $over,
        public string $spent,
        public string $cap,
        public int $percent,
        public string $settingsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->over
            ? __('Festilaw · plafond de dépense d\'IA du mois dépassé')
            : __('Festilaw · la dépense d\'IA du mois approche du plafond'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.blog-monthly-spend-warning');
    }
}
