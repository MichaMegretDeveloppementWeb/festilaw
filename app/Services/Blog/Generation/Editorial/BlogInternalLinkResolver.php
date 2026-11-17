<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Services\Web\Localization\LocalizedUrlGenerator;

/**
 * Traduit les jetons de liens internes d'un article (`festilaw:post/12`, `festilaw:page/pricing`) en adresses
 * de la langue de la version : `/fr/blog/{slug}`, `/es/pricing`. Le chemin seul est ecrit, comme un lien
 * interne saisi a la main. Un jeton qui ne mene nulle part (article absent, page inconnue) perd son lien et
 * garde son texte : un lien casse ne doit jamais entrer dans un brouillon.
 */
final readonly class BlogInternalLinkResolver
{
    /**
     * Les pages du site qu'un article peut citer : cle du jeton => [route, ancre].
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    public const PAGES = [
        'quiz' => ['home', 'quiz'],
        'pricing' => ['pricing', null],
        'understand-gpsr' => ['understand-gpsr', null],
        'services' => ['services', null],
        'excluded-products' => ['excluded-products', null],
        'contact' => ['contact', null],
    ];

    public function __construct(private LocalizedUrlGenerator $urls) {}

    /**
     * @param  array<int, array<string, string>>  $postSlugs  les slugs des articles citables : id => langue => slug
     */
    public function resolve(string $markdown, string $locale, array $postSlugs): string
    {
        return (string) preg_replace_callback(
            '~\[([^\]]*)\]\(festilaw:(post|page)/([a-z0-9-]+)\)~',
            function (array $match) use ($locale, $postSlugs): string {
                $path = $this->path($match[2], $match[3], $locale, $postSlugs);

                return $path === null ? $match[1] : '['.$match[1].']('.$path.')';
            },
            $markdown,
        );
    }

    /** @param  array<int, array<string, string>>  $postSlugs */
    private function path(string $type, string $key, string $locale, array $postSlugs): ?string
    {
        if ($type === 'post') {
            $slug = $postSlugs[(int) $key][$locale] ?? null;

            return $slug === null || ! ctype_digit($key) ? null : $this->pathOf($this->urls->route('blog.show', ['slug' => $slug], $locale));
        }

        if (! isset(self::PAGES[$key])) {
            return null;
        }

        [$route, $anchor] = self::PAGES[$key];

        return $this->pathOf($this->urls->route($route, [], $locale)).($anchor !== null ? '#'.$anchor : '');
    }

    private function pathOf(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?: '/';
    }
}
