<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Repositories\SettingRepository;
use App\Services\Blog\Generation\BlogAiSettingsService;

/**
 * Enregistre les reglages de la generation par IA. Une seule ecriture (une cle JSON) : tous les reglages
 * changent ensemble ou pas du tout. Les valeurs sont validees en amont par l'ecran.
 */
final readonly class UpdateBlogAiSettingsAction
{
    public function __construct(private SettingRepository $settings) {}

    public function execute(BlogAiSettings $settings): void
    {
        $this->settings->put(
            BlogAiSettingsService::KEY,
            json_encode($settings->toStored(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
