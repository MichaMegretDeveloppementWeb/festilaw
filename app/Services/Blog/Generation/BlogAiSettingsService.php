<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Repositories\SettingRepository;

/**
 * Lecture des reglages de la generation par IA : ce que le back-office a enregistre (une seule cle de la
 * table settings, en JSON), complete par les valeurs de depart. Une lecture = une requete, sans cache : le
 * worker vit longtemps et doit voir un reglage change pendant qu'il tourne.
 */
final readonly class BlogAiSettingsService
{
    public const KEY = 'blog.ai';

    /**
     * @param  array<string, mixed>  $defaults
     * @param  list<string>  $locales
     */
    public function __construct(
        private SettingRepository $settings,
        private ArticleLengthRules $length,
        private array $defaults,
        private string $fallbackEmail,
        private array $locales,
    ) {}

    public function current(): BlogAiSettings
    {
        $stored = json_decode((string) $this->settings->get(self::KEY), true);

        return BlogAiSettings::fromStored(
            is_array($stored) ? $stored : [],
            $this->defaults,
            $this->fallbackEmail,
            $this->locales,
            $this->length,
        );
    }
}
