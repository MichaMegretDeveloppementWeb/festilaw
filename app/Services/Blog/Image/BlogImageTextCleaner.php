<?php

declare(strict_types=1);

namespace App\Services\Blog\Image;

use App\Services\Blog\Content\BlogLinkPolicy;

/**
 * Nettoie les textes qui accompagnent une image avant de les enregistrer : descriptions par langue
 * et credit. Calcul pur.
 */
final readonly class BlogImageTextCleaner
{
    private const MAX_ALT_LENGTH = 300;

    private const MAX_CREDIT_LENGTH = 200;

    /**
     * @param  list<string>  $locales  langues du site
     */
    public function __construct(
        private BlogLinkPolicy $links,
        private array $locales,
    ) {}

    /**
     * Ne garde que les langues du site et les descriptions non vides.
     *
     * @param  array<string, mixed>  $alt
     * @return array<string, string>|null
     */
    public function alt(array $alt): ?array
    {
        $clean = [];

        foreach ($this->locales as $locale) {
            $text = $this->line($alt[$locale] ?? null, self::MAX_ALT_LENGTH);

            if ($text !== null) {
                $clean[$locale] = $text;
            }
        }

        return $clean === [] ? null : $clean;
    }

    public function credit(mixed $text): ?string
    {
        return $this->line($text, self::MAX_CREDIT_LENGTH);
    }

    /** Lien de credit : une adresse web complete seulement, sinon rien. */
    public function creditUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return preg_match('#^https?://#i', $url) === 1 && $this->links->isAllowed($url) ? $url : null;
    }

    private function line(mixed $text, int $maxLength): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }
}
