<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Le texte d'un article en cours de generation, dans une langue : titre, extrait, titre et description pour
 * Google, corps en Markdown restreint (avec ses jetons de liens et ses emplacements d'images). C'est ce que
 * se passent la redaction, la verification, le style et les traductions, sous la forme gardee au journal
 * de chaque etape.
 */
final readonly class EditorialArticle
{
    public function __construct(
        public string $title,
        public string $excerpt,
        public string $metaTitle,
        public string $metaDescription,
        public string $body,
    ) {}

    /**
     * Depuis la reponse d'un agent (`body_markdown`) ou depuis le journal d'une etape (`body`).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string ...$keys): string => trim((string) (collect($keys)->map(fn (string $key) => $data[$key] ?? null)->first(fn ($value) => is_string($value)) ?? ''));

        return new self(
            title: $text('title'),
            excerpt: $text('excerpt'),
            metaTitle: $text('meta_title'),
            metaDescription: $text('meta_description'),
            body: $text('body_markdown', 'body'),
        );
    }

    /** @return array{title: string, excerpt: string, meta_title: string, meta_description: string, body: string} */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'body' => $this->body,
        ];
    }

    /** Tout le texte, pour comparer deux versions (garde des faits). */
    public function text(): string
    {
        return implode("\n\n", [$this->title, $this->excerpt, $this->metaTitle, $this->metaDescription, $this->body]);
    }

    public function isEmpty(): bool
    {
        return $this->title === '' || $this->body === '';
    }

    /** Ce qu'un agent doit lire : l'article sous forme de JSON lisible. */
    public function forPrompt(): string
    {
        return (string) json_encode([
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'body_markdown' => $this->body,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
