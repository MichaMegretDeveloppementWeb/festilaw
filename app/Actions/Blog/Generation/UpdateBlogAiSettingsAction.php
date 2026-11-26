<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Repositories\SettingRepository;
use App\Services\Blog\Generation\BlogAiSettingsService;
use Carbon\CarbonImmutable;

/**
 * Enregistre les reglages de la generation par IA. Une seule ecriture (une cle JSON) : tous les reglages
 * changent ensemble ou pas du tout. Les valeurs sont validees en amont par l'ecran.
 *
 * Le point de depart du calendrier de la generation automatique se decide ici, par rapport aux reglages en place
 * (BlogAiSettings::withCalendarFrom) : il ne repart de maintenant que si la generation est activee, ou si son
 * rythme, son jour ou son heure changent.
 */
final readonly class UpdateBlogAiSettingsAction
{
    public function __construct(
        private SettingRepository $settings,
        private BlogAiSettingsService $current,
    ) {}

    public function execute(BlogAiSettings $settings): BlogAiSettings
    {
        $saved = $settings->withCalendarFrom($this->current->current(), CarbonImmutable::now());

        $this->settings->put(
            BlogAiSettingsService::KEY,
            json_encode($saved->toStored(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        return $saved;
    }
}
