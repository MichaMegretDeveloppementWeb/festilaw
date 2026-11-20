<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Data\Blog\Generation\Health\BlogGenerationHealth;
use App\Repositories\SettingRepository;

/** Le resultat de la derniere verification quotidienne des services de la generation, garde dans `settings`. */
final readonly class BlogGenerationHealthRepository
{
    public const KEY = 'blog.ai.health';

    public function __construct(private SettingRepository $settings) {}

    public function latest(): ?BlogGenerationHealth
    {
        return BlogGenerationHealth::fromStored($this->settings->get(self::KEY));
    }

    public function store(BlogGenerationHealth $health): void
    {
        $this->settings->put(self::KEY, $health->toStored());
    }
}
