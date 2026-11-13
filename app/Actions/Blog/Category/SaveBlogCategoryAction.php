<?php

declare(strict_types=1);

namespace App\Actions\Blog\Category;

use App\Data\Blog\SaveBlogCategoryData;
use App\Exceptions\BaseAppException;
use App\Exceptions\Blog\BlogCategoryException;
use App\Exceptions\Blog\BlogSlugException;
use App\Models\BlogCategory;
use App\Services\Blog\Slug\BlogCategorySlugAssigner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Cree ou modifie une categorie du blog et ses textes dans chaque langue. Le nom est obligatoire
 * dans toutes les langues du site : une categorie a une page publique dans chacune. Une nouvelle
 * categorie se place en dernier.
 */
final readonly class SaveBlogCategoryAction
{
    private const MAX_LINE = 255;

    /**
     * @param  list<string>  $locales  langues du site
     */
    public function __construct(
        private BlogCategorySlugAssigner $slugs,
        private array $locales,
    ) {}

    /**
     * @throws BaseAppException
     */
    public function execute(SaveBlogCategoryData $data): BlogCategory
    {
        $names = [];

        foreach ($this->locales as $locale) {
            $names[$locale] = $this->line($data->translation($locale)->name, self::MAX_LINE)
                ?? throw BlogCategoryException::nameMissing($locale);
        }

        try {
            return DB::transaction(function () use ($data, $names): BlogCategory {
                $category = $this->lockOrCreate($data->categoryId);

                foreach ($this->locales as $locale) {
                    $existing = $category->translationFor($locale);
                    $input = $data->translation($locale);
                    $slug = $this->slugs->resolve($category->id, $locale, $input->slug, $names[$locale]);

                    $category->translations()->updateOrCreate(['locale' => $locale], [
                        'name' => $names[$locale],
                        'slug' => $slug,
                        'description' => $this->line($input->description, 2000),
                        'meta_title' => $this->line($input->metaTitle, self::MAX_LINE),
                        'meta_description' => $this->line($input->metaDescription, self::MAX_LINE),
                    ]);

                    $this->slugs->record($category, $locale, $existing?->slug, $slug);
                }

                return $category->load('translations');
            });
        } catch (UniqueConstraintViolationException $e) {
            throw BlogSlugException::takenOnWrite($e);
        }
    }

    private function lockOrCreate(?int $categoryId): BlogCategory
    {
        if ($categoryId === null) {
            $category = BlogCategory::query()->create([
                'position' => ((int) BlogCategory::query()->max('position')) + 1,
            ]);

            return $category->setRelation('translations', $category->newCollection());
        }

        return BlogCategory::query()->with('translations')->lockForUpdate()->find($categoryId)
            ?? throw BlogCategoryException::notFound($categoryId);
    }

    private function line(?string $text, int $maxLength): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }
}
