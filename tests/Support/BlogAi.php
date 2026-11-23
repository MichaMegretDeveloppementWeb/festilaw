<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Blog\Generation\UpdateBlogAiSettingsAction;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Repositories\Blog\OpenRouterKeyRepository;
use Carbon\CarbonImmutable;

/**
 * Aide des tests de la generation par IA : enregistre des reglages, et la cle OpenRouter, comme le ferait le
 * back-office.
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

    /**
     * Enregistre une cle OpenRouter comme apres sa verification dans les reglages (elle ne vit plus dans le .env,
     * decision du 23/11), sans appeler OpenRouter.
     */
    public static function saveOpenRouterKey(string $secret = 'sk-or-v1-test-key-0000000000000000', ?CarbonImmutable $expiresAt = null, ?CarbonImmutable $savedAt = null): void
    {
        app(OpenRouterKeyRepository::class)->store($secret, $expiresAt, $savedAt ?? CarbonImmutable::now());
    }
}
