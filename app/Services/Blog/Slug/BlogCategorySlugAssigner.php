<?php

declare(strict_types=1);

namespace App\Services\Blog\Slug;

use App\Exceptions\Blog\BlogSlugException;
use App\Models\BlogCategory;
use App\Models\BlogCategoryRetiredSlug;
use App\Repositories\Blog\BlogCategorySlugRepository;

/**
 * Decide le slug d'une categorie dans une langue, puis tient l'historique de ses anciens slugs.
 * Appele dans la transaction de l'Action : n'ouvre aucune transaction. Pas de mot reserve ici : une
 * categorie vit sous son propre segment d'adresse.
 */
final readonly class BlogCategorySlugAssigner
{
    public function __construct(
        private BlogSlugGenerator $generator,
        private BlogCategorySlugRepository $slugs,
    ) {}

    /**
     * Slug saisi : normalise, refuse s'il est pris. Slug vide : genere depuis le nom, avec suffixe au
     * besoin. Null si ni slug ni nom exploitable.
     *
     * @throws BlogSlugException
     */
    public function resolve(?int $categoryId, string $locale, ?string $typedSlug, ?string $name): ?string
    {
        $typed = $this->generator->fromText((string) $typedSlug);

        if ($typed !== '') {
            if ($this->slugs->isTaken($locale, $typed, $categoryId)) {
                throw BlogSlugException::categoryTaken($locale, $typed);
            }

            return $typed;
        }

        $base = $this->generator->fromText((string) $name);

        if ($base === '') {
            return null;
        }

        return $this->generator->firstAvailable(
            $base,
            fn (string $candidate): bool => $this->slugs->isTaken($locale, $candidate, $categoryId),
        );
    }

    /** Tout ancien slug d'une categorie est garde (redirection 301) ; le slug attribue sort de l'historique. */
    public function record(BlogCategory $category, string $locale, ?string $previousSlug, ?string $newSlug): void
    {
        if ($previousSlug === $newSlug) {
            return;
        }

        if ($newSlug !== null) {
            BlogCategoryRetiredSlug::query()
                ->where('blog_category_id', $category->id)
                ->where('locale', $locale)
                ->where('slug', $newSlug)
                ->delete();
        }

        if ($previousSlug !== null) {
            BlogCategoryRetiredSlug::query()->updateOrCreate(
                ['locale' => $locale, 'slug' => $previousSlug],
                ['blog_category_id' => $category->id],
            );
        }
    }
}
