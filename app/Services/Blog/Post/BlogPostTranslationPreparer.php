<?php

declare(strict_types=1);

namespace App\Services\Blog\Post;

use App\Data\Blog\BlogPostTranslationData;
use App\Data\Blog\NormalizedBlogContent;

/**
 * Prepare les textes d'une langue avant enregistrement : espaces nettoyes, longueurs bornees, champ
 * vide ramene a null. Calcul pur. Le slug et le HTML n'en font pas partie : ils dependent de la base
 * (unicite) et des images, et sont decides par l'Action.
 */
final readonly class BlogPostTranslationPreparer
{
    private const MAX_LINE = 255;

    private const MAX_EXCERPT = 500;

    /**
     * @return array{title: ?string, excerpt: ?string, content: ?array<string, mixed>, meta_title: ?string, meta_description: ?string, word_count: int}
     */
    public function prepare(BlogPostTranslationData $data, NormalizedBlogContent $content): array
    {
        return [
            'title' => $this->line($data->title, self::MAX_LINE),
            'excerpt' => $this->line($data->excerpt, self::MAX_EXCERPT),
            'content' => $content->document,
            'meta_title' => $this->line($data->metaTitle, self::MAX_LINE),
            'meta_description' => $this->line($data->metaDescription, self::MAX_LINE),
            'word_count' => $content->wordCount,
        ];
    }

    private function line(?string $text, int $maxLength): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }
}
