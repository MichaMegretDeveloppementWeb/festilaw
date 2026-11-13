<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Services\Blog\Slug\BlogCategorySlugAssigner;
use App\Services\Blog\Slug\BlogPostSlugAssigner;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Support\Str;

/**
 * Propose l'adresse d'un article ou d'une categorie a partir de son titre ou de son nom, pour l'afficher
 * dans le formulaire avant l'enregistrement : meme generateur et meme regle d'unicite qu'a
 * l'enregistrement. Lecture seule (1 requete, une de plus par suffixe quand l'adresse est deja prise).
 */
final readonly class BlogSlugSuggestionService
{
    private const PLACEHOLDER = 'slug-placeholder';

    public function __construct(
        private BlogPostSlugAssigner $slugs,
        private BlogCategorySlugAssigner $categorySlugs,
        private LocalizedUrlGenerator $urls,
    ) {}

    /** Chaine vide quand le titre ne donne aucune adresse (titre vide ou fait de ponctuation). */
    public function suggest(string $locale, string $title, ?int $exceptPostId): string
    {
        return $this->slugs->resolve($exceptPostId, $locale, null, $title) ?? '';
    }

    /** Meme chose pour une categorie, a partir de son nom. */
    public function suggestForCategory(string $locale, string $name, ?int $exceptCategoryId): string
    {
        return $this->categorySlugs->resolve($exceptCategoryId, $locale, null, $name) ?? '';
    }

    /**
     * Debut de l'adresse publique dans chaque langue, affiche devant le champ : "/fr/blog/". Aucune requete.
     *
     * @param  string  $routeName  route publique qui prend un parametre "slug" (blog.show, blog.category)
     * @return array<string, string> langue => debut du chemin
     */
    public function pathPrefixes(string $routeName): array
    {
        $prefixes = [];

        foreach ($this->urls->locales() as $locale) {
            $path = (string) parse_url($this->urls->route($routeName, ['slug' => self::PLACEHOLDER], $locale), PHP_URL_PATH);

            $prefixes[$locale] = Str::before($path, self::PLACEHOLDER);
        }

        return $prefixes;
    }
}
