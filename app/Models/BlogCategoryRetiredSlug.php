<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BlogCategoryRetiredSlugFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ancien slug d'une categorie : redirection 301 vers son slug actuel.
 */
class BlogCategoryRetiredSlug extends Model
{
    /** @use HasFactory<BlogCategoryRetiredSlugFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_category_id',
        'locale',
        'slug',
    ];

    /** @return BelongsTo<BlogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }
}
