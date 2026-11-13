<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BlogPostRetiredSlugFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ancien slug d'un article. Avec un article : redirection 301 vers son slug actuel. Sans article
 * (supprime) : reponse 410.
 */
class BlogPostRetiredSlug extends Model
{
    /** @use HasFactory<BlogPostRetiredSlugFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_post_id',
        'locale',
        'slug',
    ];

    /** @return BelongsTo<BlogPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }
}
