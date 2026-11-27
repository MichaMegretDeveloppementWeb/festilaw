<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Ce que l'etape du choix de la categorie doit connaitre du blog : chaque categorie, avec les titres de ses
 * derniers articles, pour juger ce qu'elle contient vraiment et pas seulement son nom (proposition de Micha du
 * 27/11). Deux requetes quel que soit le nombre d'articles : la limite par categorie se fait en SQL
 * (`ROW_NUMBER()`, MySQL 8, MariaDB 10.2 et SQLite 3.25 au moins).
 */
final class BlogCategoryFilingRepository
{
    /**
     * Les categories dans l'ordre du menu : leur nom anglais (a defaut francais), donne au modele, leur nom francais
     * (a defaut anglais), pour le compte rendu, et les titres anglais (a defaut francais) de leurs derniers articles,
     * tous statuts, du plus recent au plus ancien.
     *
     * @return list<array{id: int, name: string, name_fr: string, titles: list<string>}>
     */
    public function categoriesWithTitles(int $titlesPerCategory): array
    {
        $titles = $this->latestTitles($titlesPerCategory);

        return BlogCategory::query()->toBase()
            ->leftJoin('blog_category_translations as en', $this->translation('en', 'blog_category_id', 'blog_categories.id'))
            ->leftJoin('blog_category_translations as fr', $this->translation('fr', 'blog_category_id', 'blog_categories.id'))
            ->orderBy('blog_categories.position')
            ->orderBy('blog_categories.id')
            ->select(['blog_categories.id', 'en.name as name_en', 'fr.name as name_fr'])
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name_en ?: $row->name_fr),
                'name_fr' => (string) ($row->name_fr ?: $row->name_en),
                'titles' => $titles[(int) $row->id] ?? [],
            ])
            ->filter(static fn (array $category): bool => $category['name'] !== '')
            ->values()
            ->all();
    }

    /** @return array<int, list<string>> les titres de chaque categorie, les plus recents d'abord */
    private function latestTitles(int $perCategory): array
    {
        if ($perCategory < 1) {
            return [];
        }

        $ranked = BlogPost::query()->toBase()
            ->leftJoin('blog_post_translations as en', $this->translation('en', 'blog_post_id', 'blog_posts.id'))
            ->leftJoin('blog_post_translations as fr', $this->translation('fr', 'blog_post_id', 'blog_posts.id'))
            ->whereNotNull('blog_posts.blog_category_id')
            ->select(['blog_posts.blog_category_id', 'blog_posts.id'])
            ->selectRaw("COALESCE(NULLIF(en.title, ''), fr.title) AS title")
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY blog_posts.blog_category_id ORDER BY blog_posts.id DESC) AS position');

        $titles = [];

        foreach (DB::query()->fromSub($ranked, 'ranked')->where('position', '<=', $perCategory)->orderBy('blog_category_id')->orderBy('position')->get() as $row) {
            if (filled($row->title)) {
                $titles[(int) $row->blog_category_id][] = (string) $row->title;
            }
        }

        return $titles;
    }

    /** La traduction d'une langue, jointe a sa ligne. */
    private function translation(string $locale, string $foreignKey, string $ownerKey): callable
    {
        return static function (JoinClause $join) use ($locale, $foreignKey, $ownerKey): void {
            $join->on("{$locale}.{$foreignKey}", '=', $ownerKey)->where("{$locale}.locale", '=', $locale);
        };
    }
}
