<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogCategoryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogCategory>
 */
class BlogCategoryFactory extends Factory
{
    /**
     * Par defaut : une categorie sans texte. Utiliser withTranslations() pour l'habiller.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => 0,
        ];
    }

    /** Categorie avec un nom et un slug dans chacune des langues du site. */
    public function withTranslations(): static
    {
        return $this->afterCreating(function (BlogCategory $category): void {
            foreach (config('festilaw.supported_locales') as $locale) {
                BlogCategoryTranslation::factory()->for($category, 'category')->create(['locale' => $locale]);
            }
        });
    }
}
