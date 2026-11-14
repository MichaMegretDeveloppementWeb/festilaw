<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;

/**
 * Reglages de la generation par IA, tels que le back-office les enregistre (cle `blog.ai` de la table
 * settings). Toujours complet et coherent : ce qui manque ou sort des bornes est remplace par la valeur de
 * depart ou ramene dans les bornes par `fromStored()`.
 */
final readonly class BlogAiSettings
{
    public const MAX_IMAGES = 3;

    /**
     * @param  array<string, string>  $models  identifiant de modele par etape (sujet, recherche, redaction...)
     * @param  array<string, string>  $transparencyNotice  mention de transparence par langue (vide = aucune)
     */
    public function __construct(
        public bool $enabled,
        public int $periodCount,
        public BlogAiPeriodUnit $periodUnit,
        public int $articlesPerRun,
        public int $pendingDraftsCap,
        public int $monthlySpendCapCents,
        public string $priorityTopics,
        public string $avoidedTopics,
        public array $models,
        public BlogAiImageMode $imageMode,
        public int $maxImages,
        public string $notificationEmail,
        public array $transparencyNotice,
    ) {}

    /**
     * @param  array<string, mixed>  $stored  ce qui est enregistre (peut etre vide ou partiel)
     * @param  array<string, mixed>  $defaults  valeurs de depart (config/blog.php, section ai.defaults)
     * @param  list<string>  $locales
     */
    public static function fromStored(array $stored, array $defaults, string $fallbackEmail, array $locales): self
    {
        $value = static fn (string $key): mixed => $stored[$key] ?? $defaults[$key] ?? null;

        $models = [];
        foreach ((array) ($defaults['models'] ?? []) as $step => $default) {
            $chosen = trim((string) ($stored['models'][$step] ?? ''));
            $models[(string) $step] = $chosen !== '' ? $chosen : (string) $default;
        }

        $notice = [];
        foreach ($locales as $locale) {
            $notice[$locale] = trim((string) ($stored['transparency_notice'][$locale] ?? $defaults['transparency_notice'][$locale] ?? ''));
        }

        $email = trim((string) $value('notification_email'));

        return new self(
            enabled: (bool) $value('enabled'),
            periodCount: max(1, (int) $value('period_count')),
            periodUnit: BlogAiPeriodUnit::tryFrom((string) $value('period_unit')) ?? BlogAiPeriodUnit::Week,
            articlesPerRun: max(1, (int) $value('articles_per_run')),
            pendingDraftsCap: max(1, (int) $value('pending_drafts_cap')),
            monthlySpendCapCents: max(0, (int) $value('monthly_spend_cap_cents')),
            priorityTopics: trim((string) $value('priority_topics')),
            avoidedTopics: trim((string) $value('avoided_topics')),
            models: $models,
            imageMode: BlogAiImageMode::tryFrom((string) $value('image_mode')) ?? BlogAiImageMode::Stock,
            maxImages: min(self::MAX_IMAGES, max(1, (int) $value('max_images'))),
            notificationEmail: $email !== '' ? $email : $fallbackEmail,
            transparencyNotice: $notice,
        );
    }

    /** Plafond de depense du mois, dans l'unite des couts enregistres (millioniemes de dollar). */
    public function monthlySpendCapMicrousd(): int
    {
        return $this->monthlySpendCapCents * 10_000;
    }

    public function modelFor(string $step): ?string
    {
        return $this->models[$step] ?? null;
    }

    /**
     * La forme enregistree.
     *
     * @return array<string, mixed>
     */
    public function toStored(): array
    {
        return [
            'enabled' => $this->enabled,
            'period_count' => $this->periodCount,
            'period_unit' => $this->periodUnit->value,
            'articles_per_run' => $this->articlesPerRun,
            'pending_drafts_cap' => $this->pendingDraftsCap,
            'monthly_spend_cap_cents' => $this->monthlySpendCapCents,
            'priority_topics' => $this->priorityTopics,
            'avoided_topics' => $this->avoidedTopics,
            'models' => $this->models,
            'image_mode' => $this->imageMode->value,
            'max_images' => $this->maxImages,
            'notification_email' => $this->notificationEmail,
            'transparency_notice' => $this->transparencyNotice,
        ];
    }
}
