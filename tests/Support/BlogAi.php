<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Blog\Generation\UpdateBlogAiSettingsAction;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\BlogAiSettings;

/**
 * Aide des tests de la generation par IA : enregistre des reglages comme le ferait le back-office.
 */
final class BlogAi
{
    /**
     * Enregistre les reglages de depart, modifies par `$overrides` (cles de la forme enregistree, par
     * exemple `enabled`, `articles_per_run`, `monthly_spend_cap_cents`).
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function saveSettings(array $overrides = []): BlogAiSettings
    {
        $settings = BlogAiSettings::fromStored(
            $overrides,
            config('blog.ai.defaults'),
            (string) config('festilaw.notification_email'),
            app(ArticleLengthRules::class),
        );

        app(UpdateBlogAiSettingsAction::class)->execute($settings);

        return $settings;
    }
}
