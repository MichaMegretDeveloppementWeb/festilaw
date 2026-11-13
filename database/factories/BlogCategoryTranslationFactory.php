<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogCategoryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogCategoryTranslation>
 */
class BlogCategoryTranslationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::ucfirst(fake()->unique()->words(2, true));

        return [
            'blog_category_id' => BlogCategory::factory(),
            'locale' => 'en',
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
        ];
    }
}
