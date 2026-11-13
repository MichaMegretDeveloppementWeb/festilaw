<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Article du blog, partie commune aux trois langues. Les textes sont dans BlogPostTranslation (une
 * ligne par langue). Un article n'est publie ou programme que si ses trois langues sont redigees :
 * elles sont publiees ensemble.
 */
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_category_id',
        'featured_image_id',
        'share_image_id',
        'status',
        'origin',
        'scheduled_for',
        'published_at',
        'content_updated_at',
        'sources',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'origin' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'origin' => BlogPostOrigin::class,
            'scheduled_for' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'content_updated_at' => 'immutable_datetime',
            'sources' => 'array',
        ];
    }

    /** @return BelongsTo<BlogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /** @return HasMany<BlogPostTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(BlogPostTranslation::class);
    }

    /** @return BelongsTo<BlogImage, $this> */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(BlogImage::class, 'featured_image_id');
    }

    /** @return BelongsTo<BlogImage, $this> */
    public function shareImage(): BelongsTo
    {
        return $this->belongsTo(BlogImage::class, 'share_image_id');
    }

    /**
     * Toutes les images rattachees a l'article (principale, partage, images du contenu).
     *
     * @return HasMany<BlogImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(BlogImage::class);
    }

    /**
     * Articles associes choisis a la main, dans l'ordre voulu.
     *
     * @return BelongsToMany<BlogPost, $this>
     */
    public function relatedPosts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'blog_post_related', 'blog_post_id', 'related_post_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /** @return HasMany<BlogPostRetiredSlug, $this> */
    public function retiredSlugs(): HasMany
    {
        return $this->hasMany(BlogPostRetiredSlug::class);
    }

    /** Textes de la langue demandee. La relation `translations` doit avoir ete prechargee. */
    public function translationFor(string $locale): ?BlogPostTranslation
    {
        return $this->translations->firstWhere('locale', $locale);
    }

    /** Deja publie au moins une fois : ses adresses ont pu etre vues, donc un slug change ou supprime laisse une trace. */
    public function hasBeenPublished(): bool
    {
        return $this->published_at !== null;
    }
}
