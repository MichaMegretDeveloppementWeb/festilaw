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
     */
    public function __construct(
        public ?int $categoryId,
        public array $translations,
    ) {}

    public function translation(string $locale): BlogCategoryTranslationData
    {
        return $this->translations[$locale] ?? new BlogCategoryTranslationData;
    }
}
