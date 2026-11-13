<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPostTranslation>
 */
class BlogPostTranslationFactory extends Factory
{
    /**
     * Par defaut : une langue redigee (titre, slug, un paragraphe de contenu et son rendu).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::ucfirst(fake()->unique()->words(5, true));
        $paragraph = fake()->sentence(12);

        return [
            'blog_post_id' => BlogPost::factory(),
            'locale' => 'en',
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(),
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]],
                ],
            ],
            'content_html' => '<p>'.e($paragraph).'</p>',
            'word_count' => str_word_count($paragraph),
        ];
    }

    /** Langue pas encore redigee : la ligne existe, les champs sont vides. */
    public function blank(): static
    {
        return $this->state(fn (): array => [
            'title' => null,
            'slug' => null,
            'excerpt' => null,
            'content' => null,
            'content_html' => null,
            'word_count' => 0,
        ]);
    }
}
