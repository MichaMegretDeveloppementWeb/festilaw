<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BlogPostTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Textes d'un article dans une langue. `content` est le document structure (source), `content_html`
 * son rendu produit par le serveur.
 */
class BlogPostTranslation extends Model
{
    /** @use HasFactory<BlogPostTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_post_id',
        'locale',
        'title',
        'slug',
        'excerpt',
        'content',
        'content_html',
        'meta_title',
        'meta_description',
        'word_count',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'word_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'word_count' => 'integer',
        ];
    }

    /** @return BelongsTo<BlogPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /** Langue redigee : un titre, un slug et un contenu non vide. Calcule sur les attributs deja charges. */
    public function isWritten(): bool
    {
        return filled($this->title) && filled($this->slug) && $this->word_count > 0;
    }
}
