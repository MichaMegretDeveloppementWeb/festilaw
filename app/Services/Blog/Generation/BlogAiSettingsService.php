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
     */
    public function __construct(
        private SettingRepository $settings,
        private ArticleLengthRules $length,
        private array $defaults,
        private string $fallbackEmail,
    ) {}

    public function current(): BlogAiSettings
    {
        $stored = json_decode((string) $this->settings->get(self::KEY), true);

        return BlogAiSettings::fromStored(
            is_array($stored) ? $stored : [],
            $this->defaults,
            $this->fallbackEmail,
            $this->length,
        );
    }

    /**
     * Les modeles que nous recommandons, etape par etape. Ce sont aussi les modeles de depart : ils ne s'ecrivent
     * qu'a un seul endroit (`blog.ai.defaults.models`), et le bouton « Remettre les modeles recommandes » suit
     * donc nos recommandations quand elles changent (demande de Micha du 19/11). Aucune requete.
     *
     * @return array<string, string>
     */
    public function recommendedModels(): array
    {
        return array_map(strval(...), (array) ($this->defaults['models'] ?? []));
    }
}
