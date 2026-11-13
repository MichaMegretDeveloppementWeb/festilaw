<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BlogCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categorie du blog. Les textes sont par langue (BlogCategoryTranslation) ; la ligne ne porte que
 * l'ordre d'affichage.
 */
class BlogCategory extends Model
{
    /** @use HasFactory<BlogCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /** @return HasMany<BlogCategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(BlogCategoryTranslation::class);
    }

    /** @return HasMany<BlogPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    /** @return HasMany<BlogCategoryRetiredSlug, $this> */
    public function retiredSlugs(): HasMany
    {
        return $this->hasMany(BlogCategoryRetiredSlug::class);
    }

    /** Textes de la langue demandee. La relation `translations` doit avoir ete prechargee. */
    public function translationFor(string $locale): ?BlogCategoryTranslation
    {
        return $this->translations->firstWhere('locale', $locale);
    }
}
