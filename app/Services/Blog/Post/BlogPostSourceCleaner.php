<?php

declare(strict_types=1);

namespace App\Services\Blog\Post;

use App\Data\Blog\BlogSourceData;
use App\Services\Blog\Content\BlogLinkPolicy;

/**
 * Nettoie la liste des sources d'un article : intitule sur une ligne, lien web complet. Une source
 * sans intitule ou au lien refuse est ecartee. Calcul pur.
 */
final readonly class BlogPostSourceCleaner
{
    private const MAX_LABEL = 200;

    private const MAX_SOURCES = 30;

    public function __construct(private BlogLinkPolicy $links) {}

    /**
     * @param  list<BlogSourceData>  $sources
     * @return list<array{label: string, url: string}>|null null quand il ne reste aucune source
     */
    public function clean(array $sources): ?array
    {
        $clean = [];

        foreach ($sources as $source) {
            $label = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $source->label)), 0, self::MAX_LABEL);
            $url = trim($source->url);

            if ($label === '' || preg_match('#^https?://#i', $url) !== 1 || ! $this->links->isAllowed($url)) {
                continue;
            }

            $clean[] = ['label' => $label, 'url' => $url];

            if (count($clean) === self::MAX_SOURCES) {
                break;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
