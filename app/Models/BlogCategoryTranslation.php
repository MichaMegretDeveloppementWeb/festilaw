<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BlogCategoryTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Textes d'une categorie dans une langue.
 */
class BlogCategoryTranslation extends Model
{
    /** @use HasFactory<BlogCategoryTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_category_id',
        'locale',
        'name',
        'slug',
        'description',
        'meta_title',
        'meta_description',
    ];

    /** @return BelongsTo<BlogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }
}
