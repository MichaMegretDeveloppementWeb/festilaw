<?php

declare(strict_types=1);

namespace App\Actions\Blog\Category;

use App\Exceptions\Blog\BlogCategoryException;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une categorie vide. Tant qu'elle contient des articles (brouillons compris), la
 * suppression est refusee avec leur nombre : il faut d'abord les rattacher ailleurs. La cle etrangere
 * restrictive garantit la regle meme en cas de course. Ses textes et ses anciens slugs partent avec
 * elle.
 */
final readonly class DeleteBlogCategoryAction
{
    /**
     * @return bool false si la categorie n'existait deja plus
     *
     * @throws BlogCategoryException
     */
    public function execute(int $categoryId): bool
    {
        return DB::transaction(function () use ($categoryId): bool {
            $category = BlogCategory::query()->lockForUpdate()->find($categoryId);

            if ($category === null) {
                return false;
            }

            $postCount = BlogPost::query()->where('blog_category_id', $categoryId)->count();

            if ($postCount > 0) {
                throw BlogCategoryException::notEmpty($categoryId, $postCount);
            }

            $category->delete();

            return true;
        });
    }
}
