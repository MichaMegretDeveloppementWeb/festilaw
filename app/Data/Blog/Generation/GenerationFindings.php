<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogTopicKind;
use Illuminate\Support\Str;

/**
 * Ce qu'une etape a trouve et que la generation doit garder sur sa propre ligne : le sujet choisi (etape
 * « sujet ») ou le brouillon cree (assemblage). Une etape ne touche jamais aux tables de generation ; c'est
 * le moteur qui recopie ces valeurs, dans la meme ecriture que le cout. Seules les valeurs donnees sont
 * ecrites : une etape qui ne trouve rien ne vide rien.
 */
final readonly class GenerationFindings
{
    /** Longueur de la colonne `topic_title`. */
    private const TOPIC_TITLE_MAX_LENGTH = 300;

    public function __construct(
        public ?string $topicTitle = null,
        public ?string $topicSummary = null,
        public ?BlogTopicKind $topicKind = null,
        public ?int $postId = null,
    ) {}

    /**
     * Les colonnes de `blog_generations` a ecrire.
     *
     * @return array<string, string|int>
     */
    public function columns(): array
    {
        return array_filter([
            'topic_title' => filled($this->topicTitle) ? Str::limit(trim((string) $this->topicTitle), self::TOPIC_TITLE_MAX_LENGTH, '') : null,
            'topic_summary' => filled($this->topicSummary) ? trim((string) $this->topicSummary) : null,
            'topic_kind' => $this->topicKind?->value,
            'blog_post_id' => $this->postId,
        ], static fn (string|int|null $value): bool => $value !== null);
    }
}
