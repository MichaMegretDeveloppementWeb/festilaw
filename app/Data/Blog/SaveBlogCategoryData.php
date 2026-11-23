<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Categorie a enregistrer (creation si `categoryId` est nul).
 */
final readonly class SaveBlogCategoryData
{
    /**
     * @param  array<string, BlogCategoryTranslationData>  $translations  textes par langue
     * @param  int|null  $generationId  la generation d'article qui cree la categorie (decision du 23/11) ; ne
     *                                  sert qu'a la creation
     */
    public function __construct(
        public ?int $categoryId,
        public array $translations,
        public ?int $generationId = null,
    ) {}

    public function translation(string $locale): BlogCategoryTranslationData
    {
        return $this->translations[$locale] ?? new BlogCategoryTranslationData;
    }
}
