<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Ce que l'etape « sujet » doit connaitre pour ne pas proposer un doublon. Le modele lit une liste bornee : les
 * derniers articles du blog, de tout statut (titre et extrait, en anglais quand il existe), les titres des
 * articles suivants, et les derniers sujets proposes par une generation, refuses compris. Le controle par le code
 * lit tous les titres connus, sans borne (controle du 20/11).
 */
final readonly class BlogTopicHistory
{
    /**
     * @param  list<array{id: int, status: string, title: string, excerpt: string|null}>  $posts
     * @param  list<array{title: string, rejected: bool}>  $proposedTopics
     * @param  list<string>  $olderTitles  les titres des articles plus anciens que `$posts`, bornes
     * @param  list<string>|null  $allTitles  tous les titres connus ; nul : ceux des deux listes bornees
     */
    public function __construct(
        public array $posts,
        public array $proposedTopics,
        public array $olderTitles = [],
        public ?array $allTitles = null,
    ) {}

    /**
     * Tous les titres connus, articles puis sujets proposes, pour le controle des doublons.
     *
     * @return list<string>
     */
    public function titles(): array
    {
        return $this->allTitles ?? [...array_column($this->posts, 'title'), ...$this->olderTitles, ...array_column($this->proposedTopics, 'title')];
    }
}
