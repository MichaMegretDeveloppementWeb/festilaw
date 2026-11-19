<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Enums\Blog\Generation\BlogEffortLevel;

/**
 * Reglages de la generation par IA, tels que le back-office les enregistre (cle `blog.ai` de la table
 * settings). Toujours complet et coherent : ce qui manque ou sort des bornes est remplace par la valeur de
 * depart ou ramene dans les bornes par `fromStored()`.
 *
 * Les sujets a privilegier ou a eviter et la mention de transparence ont ete retires le 19/11 (decision de
 * Micha) : un sujet se donne au lancement, et la mention n'est pas obligatoire. D'anciens reglages qui les
 * portent encore se lisent sans eux, et les perdent au prochain enregistrement.
 */
final readonly class BlogAiSettings
{
    public const MAX_IMAGES = 3;

    /**
     * @param  array<string, string>  $models  identifiant de modele par etape (sujet, recherche, redaction...)
     */
    public function __construct(
        public bool $enabled,
        public int $periodCount,
        public BlogAiPeriodUnit $periodUnit,
        public int $articlesPerRun,
        public int $pendingDraftsCap,
        public int $monthlySpendCapCents,
        public array $models,
        public BlogAiImageMode $imageMode,
        public int $maxImages,
        public string $notificationEmail,
        public int $articleWords,
        public BlogEffortLevel $effort,
    ) {}

    /**
     * @param  array<string, mixed>  $stored  ce qui est enregistre (peut etre vide ou partiel)
     * @param  array<string, mixed>  $defaults  valeurs de depart (config/blog.php, section ai.defaults)
     */
    public static function fromStored(array $stored, array $defaults, string $fallbackEmail, ArticleLengthRules $length): self
    {
        $value = static fn (string $key): mixed => $stored[$key] ?? $defaults[$key] ?? null;

        $models = [];
        foreach ((array) ($defaults['models'] ?? []) as $step => $default) {
            $chosen = trim((string) ($stored['models'][$step] ?? ''));
            $models[(string) $step] = $chosen !== '' ? $chosen : (string) $default;
        }

        $email = trim((string) $value('notification_email'));

        return new self(
            enabled: (bool) $value('enabled'),
            periodCount: max(1, (int) $value('period_count')),
            periodUnit: BlogAiPeriodUnit::tryFrom((string) $value('period_unit')) ?? BlogAiPeriodUnit::Week,
            articlesPerRun: max(1, (int) $value('articles_per_run')),
            pendingDraftsCap: max(1, (int) $value('pending_drafts_cap')),
            monthlySpendCapCents: max(0, (int) $value('monthly_spend_cap_cents')),
            models: $models,
            imageMode: BlogAiImageMode::tryFrom((string) $value('image_mode')) ?? BlogAiImageMode::Stock,
            maxImages: min(self::MAX_IMAGES, max(1, (int) $value('max_images'))),
            notificationEmail: $email !== '' ? $email : $fallbackEmail,
            articleWords: $length->clamp((int) $value('article_words')),
            effort: BlogEffortLevel::tryFrom((string) $value('effort')) ?? BlogEffortLevel::Medium,
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
     * Les memes reglages avec d'autres modeles pour certaines etapes (banc d'essai des modeles).
     *
     * @param  array<string, string>  $models  modele par reglage, a la place de celui des reglages
     */
    public function withModels(array $models): self
    {
        return new self(...[...get_object_vars($this), 'models' => [...$this->models, ...$models]]);
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
            'models' => $this->models,
            'image_mode' => $this->imageMode->value,
            'max_images' => $this->maxImages,
            'notification_email' => $this->notificationEmail,
            'article_words' => $this->articleWords,
            'effort' => $this->effort->value,
        ];
    }
}
