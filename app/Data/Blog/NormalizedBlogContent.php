<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Contenu d'un article apres validation : le document sous sa forme canonique (null s'il est vide),
 * les images qu'il cite et son nombre de mots.
 */
final readonly class NormalizedBlogContent
{
    /**
     * @param  array{type: string, content: list<array<string, mixed>>}|null  $document
     * @param  list<int>  $imageIds  identifiants des images citees, sans doublon, dans l'ordre du texte
     */
    public function __construct(
        public ?array $document,
        public array $imageIds,
        public int $wordCount,
    ) {}

    public static function empty(): self
    {
        return new self(null, [], 0);
    }

    public function isEmpty(): bool
    {
        return $this->document === null;
    }
}
