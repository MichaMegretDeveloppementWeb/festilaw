<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Recap interne (francophone) : un lancement de generation est termine (une generation lancee au
 * back-office, ou les N generations d'un lot planifie). Un seul message par lancement, envoye quand tout
 * est fini : combien ont reussi, combien ont echoue et a quelle etape, avec un lien vers chaque generation
 * et vers les articles a relire et publier. Aucun message technique : la cause d'un echec est au journal du
 * serveur.
 *
 * @phpstan-type GenerationRow array{subject: string, outcome: string, succeeded: bool, failed: bool, cost: string, url: string, draftUrl: string|null}
 */
final class BlogGenerationBatchFinished extends Mailable
{
    /**
     * @param  list<GenerationRow>  $rows
     * @param  string  $postsUrl  la liste des articles du back-office
     * @param  string  $generationsUrl  le journal des generations du back-office
     */
    public function __construct(
        public array $rows,
        public bool $scheduled,
        public string $totalCost,
        public string $postsUrl,
        public string $generationsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Festilaw · génération d\'articles terminée (:done sur :total)', [
            'done' => $this->succeededCount(),
            'total' => count($this->rows),
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.blog-generation-batch-finished', with: [
            'summary' => $this->summary(),
            'succeeded' => $this->succeededCount(),
            'failed' => $this->failedCount(),
        ]);
    }

    public function succeededCount(): int
    {
        return count(array_filter($this->rows, static fn (array $row): bool => $row['succeeded']));
    }

    public function failedCount(): int
    {
        return count(array_filter($this->rows, static fn (array $row): bool => $row['failed']));
    }

    /** « Sur 3 générations : 2 réussies, 1 en échec. » Les générations abandonnées ou arrêtées sont « sans article ». */
    public function summary(): string
    {
        $total = count($this->rows);
        $succeeded = $this->succeededCount();
        $failed = $this->failedCount();
        $others = $total - $succeeded - $failed;

        $parts = [
            trans_choice('{0} aucune réussie|{1} :count réussie|[2,*] :count réussies', $succeeded, ['count' => $succeeded]),
            trans_choice('{0} aucune en échec|[1,*] :count en échec', $failed, ['count' => $failed]),
        ];

        if ($others > 0) {
            $parts[] = __(':count sans article', ['count' => $others]);
        }

        return trans_choice('{1} Sur :count génération : :parts.|[2,*] Sur :count générations : :parts.', $total, [
            'count' => $total,
            'parts' => implode(', ', $parts),
        ]);
    }
}
