<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Blog\BlogImageCreditSource;
use App\Models\BlogImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogImage>
 */
class BlogImageFactory extends Factory
{
    /**
     * Par defaut : une image envoyee, en attente (sans article), avec ses quatre variantes et une
     * description par langue. Aucun fichier n'est ecrit : la ligne seule suffit a la plupart des tests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'blog_post_id' => null,
            'basename' => 'image',
            'variants' => ['widths' => [480, 768, 1200, 1600], 'share' => false],
            'width' => 1600,
            'height' => 900,
            'alt' => ['en' => 'A sample image', 'fr' => 'Une image d\'exemple', 'es' => 'Una imagen de ejemplo'],
            'credit_source' => BlogImageCreditSource::Upload,
        ];
    }

    /** Image qui possede aussi le JPEG de partage (image principale ou de partage). */
    public function withShare(): static
    {
        return $this->state(fn (array $attributes): array => [
            'variants' => ['widths' => $attributes['variants']['widths'] ?? [480, 768, 1200, 1600], 'share' => true],
        ]);
    }

    /** Image sans description : declenche l'alerte "description manquante". */
    public function withoutAlt(): static
    {
        return $this->state(fn (): array => ['alt' => null]);
    }
}
