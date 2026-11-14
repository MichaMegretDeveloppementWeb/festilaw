<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Recap interne (francophone) : un lancement de generation est termine (une generation lancee au
 * back-office, ou les N generations d'un lot planifie). Un seul message par lancement, envoye quand tout
 * est fini, pour relire et publier les brouillons.
 *
 * @phpstan-type GenerationRow array{subject: string, outcome: string, succeeded: bool, cost: string}
 */
final class BlogGenerationBatchFinished extends Mailable
{
    /** @param  list<GenerationRow>  $rows */
    public function __construct(
        public array $rows,
        public bool $scheduled,
        public string $totalCost,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        $done = count(array_filter($this->rows, static fn (array $row): bool => $row['succeeded']));

        return new Envelope(subject: __('Festilaw · génération d\'articles terminée (:done sur :total)', [
            'done' => $done,
            'total' => count($this->rows),
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.blog-generation-batch-finished');
    }
}
