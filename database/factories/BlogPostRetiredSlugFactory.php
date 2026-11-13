<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\BlogPostRetiredSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPostRetiredSlug>
 */
class BlogPostRetiredSlugFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory(),
            'locale' => 'en',
            'slug' => fake()->unique()->slug(3),
        ];
    }

    /** Ancien slug d'un article supprime : plus de proprietaire, reponse 410. */
    public function gone(): static
    {
        return $this->state(fn (): array => ['blog_post_id' => null]);
    }
}
