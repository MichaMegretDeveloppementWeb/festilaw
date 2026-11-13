<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * Par defaut : un brouillon manuel, sans categorie, sans image et sans texte.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => BlogPostStatus::Draft,
            'origin' => BlogPostOrigin::Manual,
        ];
    }

    /** Une ligne de texte redigee dans chacune des langues du site. */
    public function withTranslations(): static
    {
        return $this->afterCreating(function (BlogPost $post): void {
            foreach (config('festilaw.supported_locales') as $locale) {
                BlogPostTranslation::factory()->for($post, 'post')->create(['locale' => $locale]);
            }
        });
    }

    /** Tout ce qu'il faut pour publier : categorie, image principale, trois langues redigees. */
    public function ready(): static
    {
        return $this
            ->state(fn (): array => ['blog_category_id' => BlogCategory::factory()->withTranslations()])
            ->withTranslations()
            ->afterCreating(function (BlogPost $post): void {
                $image = BlogImage::factory()->withShare()->create(['blog_post_id' => $post->id]);
                $post->update(['featured_image_id' => $image->id]);
            });
    }

    public function scheduled(?CarbonImmutable $at = null): static
    {
        return $this->ready()->state(fn (): array => [
            'status' => BlogPostStatus::Scheduled,
            'scheduled_for' => $at ?? CarbonImmutable::now()->addDay(),
        ]);
    }

    public function published(?CarbonImmutable $at = null): static
    {
        return $this->ready()->state(fn (): array => [
            'status' => BlogPostStatus::Published,
            'published_at' => $at ?? CarbonImmutable::now()->subDay(),
        ]);
    }

    public function fromAi(): static
    {
        return $this->state(fn (): array => ['origin' => BlogPostOrigin::Ai]);
    }
}
