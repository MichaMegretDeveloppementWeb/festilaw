<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\BlogCategory;
use App\Models\BlogCategoryTranslation;
use App\Models\BlogPost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Contenu de blog aux textes PREVISIBLES pour les tests des pages publiques : un article ou une
 * categorie de cle "guide" a pour slug "guide-en", "guide-fr", "guide-es" et pour titre "Guide en"...
 */
final class BlogContent
{
    public const LOCALES = ['en', 'fr', 'es'];

    public static function category(string $key, int $position = 0): BlogCategory
    {
        $category = BlogCategory::factory()->create(['position' => $position]);

        foreach (self::LOCALES as $locale) {
            BlogCategoryTranslation::factory()->for($category, 'category')->create([
                'locale' => $locale,
                'name' => ucfirst($key).' '.$locale,
                'slug' => "{$key}-{$locale}",
                'description' => "About {$key} in {$locale}.",
            ]);
        }

        return $category;
    }

    /** Article publie, avec categorie, image principale et trois langues redigees. */
    public static function published(string $key, ?BlogCategory $category = null, ?CarbonImmutable $at = null): BlogPost
    {
        return self::named(BlogPost::factory()->published($at)->create($category ? ['blog_category_id' => $category->id] : []), $key);
    }

    /** Brouillon complet (pret a publier). */
    public static function draft(string $key, ?BlogCategory $category = null): BlogPost
    {
        return self::named(BlogPost::factory()->ready()->create($category ? ['blog_category_id' => $category->id] : []), $key);
    }

    public static function scheduled(string $key): BlogPost
    {
        return self::named(BlogPost::factory()->scheduled()->create(), $key);
    }

    /**
     * Beaucoup d'articles publies, inseres en masse (les factories seraient trop lentes) : sert aux
     * tests de budget de requetes. Rend les identifiants des articles, du plus ancien au plus recent.
     *
     * @return list<int>
     */
    public static function seedPublished(int $count, BlogCategory ...$categories): array
    {
        $categories = $categories === [] ? [self::category('bulk')] : $categories;
        $now = CarbonImmutable::parse('2026-06-01 10:00:00');
        $firstImage = (int) DB::table('blog_images')->max('id') + 1;
        $firstPost = (int) DB::table('blog_posts')->max('id') + 1;
        $images = $posts = $translations = [];

        for ($i = 0; $i < $count; $i++) {
            $imageId = $firstImage + $i;
            $postId = $firstPost + $i;
            $at = $now->addMinutes($i)->toDateTimeString();

            $images[] = [
                'id' => $imageId, 'uuid' => sprintf('00000000-0000-4000-8000-%012d', $imageId), 'basename' => 'bulk',
                'variants' => json_encode(['widths' => [480, 768, 1200, 1600], 'share' => true]), 'width' => 1600, 'height' => 900,
                'alt' => json_encode(['en' => 'Bulk', 'fr' => 'Lot', 'es' => 'Lote']), 'credit_source' => 'upload',
                'created_at' => $at, 'updated_at' => $at,
            ];
            $posts[] = [
                'id' => $postId, 'blog_category_id' => $categories[$i % count($categories)]->id, 'featured_image_id' => $imageId,
                'status' => 'published', 'origin' => 'manual', 'published_at' => $at, 'created_at' => $at, 'updated_at' => $at,
            ];

            foreach (self::LOCALES as $locale) {
                $translations[] = [
                    'blog_post_id' => $postId, 'locale' => $locale, 'title' => "Bulk {$i} {$locale}", 'slug' => "bulk-{$i}-{$locale}",
                    'excerpt' => "Excerpt {$i}", 'content' => json_encode(['type' => 'doc', 'content' => []]),
                    'content_html' => "<p>Body {$i}</p>", 'word_count' => 2, 'created_at' => $at, 'updated_at' => $at,
                ];
            }
        }

        foreach (array_chunk($images, 100) as $chunk) {
            DB::table('blog_images')->insert($chunk);
        }
        foreach (array_chunk($posts, 100) as $chunk) {
            DB::table('blog_posts')->insert($chunk);
        }
        foreach (array_chunk($translations, 100) as $chunk) {
            DB::table('blog_post_translations')->insert($chunk);
        }

        return range($firstPost, $firstPost + $count - 1);
    }

    /**
     * Document de contenu minimal (un paragraphe), tel que l'editeur le produit.
     *
     * @return array{type: string, content: list<array<string, mixed>>}
     */
    public static function document(string $text = 'Some words here.'): array
    {
        return ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ]];
    }

    /**
     * Contenu des trois langues d'un article, tel que le navigateur le renvoie a l'enregistrement.
     *
     * @return array<string, array<string, mixed>|null>
     */
    public static function contentsOf(BlogPost $post): array
    {
        $contents = [];

        foreach (self::LOCALES as $locale) {
            $contents[$locale] = $post->translations()->where('locale', $locale)->value('content');
        }

        return $contents;
    }

    private static function named(BlogPost $post, string $key): BlogPost
    {
        foreach (self::LOCALES as $locale) {
            $post->translations()->where('locale', $locale)->update([
                'title' => ucfirst($key).' '.$locale,
                'slug' => "{$key}-{$locale}",
                'excerpt' => "Excerpt of {$key} in {$locale}.",
                'content_html' => "<h2 id=\"part\">Part {$locale}</h2>\n<p>Body of {$key} in {$locale}.</p>",
            ]);
        }

        return $post->fresh();
    }
}
