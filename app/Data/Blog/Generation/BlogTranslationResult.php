<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Ou en est une traduction assistee : en cours, prete (avec les textes et le document de la langue visee),
 * ou sans resultat (echec, arret, generation introuvable). $field : le seul champ traduit, ou nul pour toute
 * la langue ; le formulaire ne remplace alors que ce champ.
 */
final readonly class BlogTranslationResult
{
    /**
     * @param  array{title: string, excerpt: string, metaTitle: string, metaDescription: string}|null  $texts
     * @param  array<string, mixed>|null  $content
     */
    private function __construct(
        public string $state,
        public ?string $locale = null,
        public ?array $texts = null,
        public ?array $content = null,
        public ?string $field = null,
    ) {}

    public static function pending(): self
    {
        return new self('pending');
    }

    public static function failed(): self
    {
        return new self('failed');
    }

    /**
     * @param  array{title: string, excerpt: string, metaTitle: string, metaDescription: string}  $texts
     * @param  array<string, mixed>  $content
     */
    public static function ready(string $locale, array $texts, array $content, ?string $field = null): self
    {
        return new self('ready', $locale, $texts, $content, $field);
    }

    public function isPending(): bool
    {
        return $this->state === 'pending';
    }

    public function isReady(): bool
    {
        return $this->state === 'ready';
    }
}
