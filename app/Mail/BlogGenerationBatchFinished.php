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
 * Pour un brouillon cree, la ligne donne aussi son extrait (anglais) et le nombre de sources citees.
 *
 * @phpstan-type GenerationRow array{subject: string, outcome: string, succeeded: bool, failed: bool, cost: string, url: string, draftUrl: string|null, excerpt?: string|null, sources?: int|null, newCategory?: string|null}
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

    /**
     * « Sur 3 générations, 2 ont réussi et 1 a échoué. » Une génération abandonnée ou arrêtée « n'a pas donné
     * d'article ». Quand tout a réussi : « Les 3 générations ont réussi. »
     */
    public function summary(): string
    {
        $total = count($this->rows);
        $succeeded = $this->succeededCount();
        $failed = $this->failedCount();
        $others = $total - $succeeded - $failed;

        if ($succeeded === $total) {
            return trans_choice('{1} La génération a réussi.|[2,*] Les :count générations ont réussi.', $total, ['count' => $total]);
        }

        $parts = [trans_choice('{0} aucune n\'a réussi|{1} :count a réussi|[2,*] :count ont réussi', $succeeded, ['count' => $succeeded])];

        if ($failed > 0) {
            $parts[] = trans_choice('{1} :count a échoué|[2,*] :count ont échoué', $failed, ['count' => $failed]);
        }

        if ($others > 0) {
            $parts[] = trans_choice('{1} :count n\'a pas donné d\'article|[2,*] :count n\'ont pas donné d\'article', $others, ['count' => $others]);
        }

        $last = array_pop($parts);

        return trans_choice('{1} Sur :count génération, :parts.|[2,*] Sur :count générations, :parts.', $total, [
            'count' => $total,
            'parts' => $parts === [] ? $last : implode(', ', $parts).' '.__('et').' '.$last,
        ]);
    }
}
