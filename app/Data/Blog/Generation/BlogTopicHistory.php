<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Ce que l'etape « sujet » doit connaitre pour ne pas proposer un doublon : les articles du blog, de tout
 * statut (titre et extrait, en anglais quand il existe), et les sujets deja proposes par une generation,
 * refuses compris.
 */
final readonly class BlogTopicHistory
{
    /**
     * @param  list<array{id: int, status: string, title: string, excerpt: string|null}>  $posts
     * @param  list<array{title: string, rejected: bool}>  $proposedTopics
     */
    public function __construct(
        public array $posts,
        public array $proposedTopics,
    ) {}

    /**
     * Tous les titres connus, articles puis sujets proposes, pour le controle des doublons.
     *
     * @return list<string>
     */
    public function titles(): array
    {
        return [...array_column($this->posts, 'title'), ...array_column($this->proposedTopics, 'title')];
    }
}
