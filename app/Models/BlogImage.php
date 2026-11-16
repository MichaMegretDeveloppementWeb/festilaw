<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Blog\BlogImageCreditSource;
use Database\Factories\BlogImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Image du blog. Ses fichiers (variantes WebP, JPEG de partage eventuel) vivent dans blog/{uuid}/ sur
 * le disque public ; les chemins se construisent par BlogImagePaths. Sans article (`blog_post_id`
 * nul), l'image est en attente ou orpheline et sera purgee.
 */
class BlogImage extends Model
{
    /** @use HasFactory<BlogImageFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'blog_post_id',
        'basename',
        'variants',
        'width',
        'height',
        'crop',
        'alt',
        'credit_source',
        'credit_author',
        'credit_url',
        'credit_license',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'credit_source' => 'upload',
    ];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'crop' => 'array',
            'alt' => 'array',
            'credit_source' => BlogImageCreditSource::class,
        ];
    }

    /** @return BelongsTo<BlogPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /**
     * Largeurs des variantes WebP disponibles, par ordre croissant.
     *
     * @return list<int>
     */
    public function widths(): array
    {
        $widths = array_map(intval(...), $this->variants['widths'] ?? []);
        sort($widths);

        return $widths;
    }

    public function hasShareVariant(): bool
    {
        return (bool) ($this->variants['share'] ?? false);
    }

    /** La source non decoupee a ete gardee : l'image peut etre recadree de nouveau. */
    public function hasSource(): bool
    {
        return (bool) ($this->variants['source'] ?? false);
    }

    /** Description de l'image dans la langue demandee (chaine vide si elle n'est pas renseignee). */
    public function altFor(string $locale): string
    {
        return trim((string) ($this->alt[$locale] ?? ''));
    }
}
