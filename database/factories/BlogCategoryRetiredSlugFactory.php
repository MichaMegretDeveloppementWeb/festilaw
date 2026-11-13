<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogCategoryRetiredSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogCategoryRetiredSlug>
 */
class BlogCategoryRetiredSlugFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_category_id' => BlogCategory::factory(),
            'locale' => 'en',
            'slug' => fake()->unique()->slug(2),
        ];
    }
}
