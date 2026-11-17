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

    /** Nombre de sujets qu'une liste (a privilegier, a eviter) peut porter, et longueur d'un sujet. */
    public const MAX_TOPICS = 40;

    public const TOPIC_MAX_LENGTH = 120;

    /**
     * @param  list<string>  $priorityTopics  sujets et mots-cles que l'IA doit privilegier
     * @param  list<string>  $avoidedTopics  sujets qu'elle doit laisser de cote
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
        public int $articleSpendCapCents,
        public array $priorityTopics,
        public array $avoidedTopics,
        public array $models,
        public BlogAiImageMode $imageMode,
        public int $maxImages,
        public string $notificationEmail,
        public array $transparencyNotice,
        public int $articleWords,
    ) {}

    /**
     * @param  array<string, mixed>  $stored  ce qui est enregistre (peut etre vide ou partiel)
     * @param  array<string, mixed>  $defaults  valeurs de depart (config/blog.php, section ai.defaults)
     * @param  list<string>  $locales
     */
    public static function fromStored(array $stored, array $defaults, string $fallbackEmail, array $locales, ArticleLengthRules $length): self
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
            articleSpendCapCents: max(1, (int) $value('article_spend_cap_cents')),
            priorityTopics: self::topics($value('priority_topics')),
            avoidedTopics: self::topics($value('avoided_topics')),
            models: $models,
            imageMode: BlogAiImageMode::tryFrom((string) $value('image_mode')) ?? BlogAiImageMode::Stock,
            maxImages: min(self::MAX_IMAGES, max(1, (int) $value('max_images'))),
            notificationEmail: $email !== '' ? $email : $fallbackEmail,
            transparencyNotice: $notice,
            articleWords: $length->clamp((int) $value('article_words')),
        );
    }

    /**
     * Une liste de sujets propre : sans vide ni doublon, bornee en nombre et en longueur. Accepte aussi
     * l'ancienne forme enregistree, un texte avec un sujet par ligne.
     *
     * @return list<string>
     */
    public static function topics(mixed $stored): array
    {
        $items = is_string($stored) ? (preg_split('/\R/u', $stored) ?: []) : (is_array($stored) ? $stored : []);
        $topics = [];

        foreach ($items as $item) {
            $topic = is_string($item) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $item) ?? ''), 0, self::TOPIC_MAX_LENGTH) : '';

            if ($topic !== '' && ! in_array(mb_strtolower($topic), array_map(mb_strtolower(...), $topics), true)) {
                $topics[] = $topic;
            }
        }

        return array_slice($topics, 0, self::MAX_TOPICS);
    }

    /** Plafond de depense du mois, dans l'unite des couts enregistres (millioniemes de dollar). */
    public function monthlySpendCapMicrousd(): int
    {
        return $this->monthlySpendCapCents * 10_000;
    }

    /** Plafond de depense d'une generation, dans l'unite des couts enregistres (millioniemes de dollar). */
    public function articleSpendCapMicrousd(): int
    {
        return $this->articleSpendCapCents * 10_000;
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
            'article_spend_cap_cents' => $this->articleSpendCapCents,
            'priority_topics' => $this->priorityTopics,
            'avoided_topics' => $this->avoidedTopics,
            'models' => $this->models,
            'image_mode' => $this->imageMode->value,
            'max_images' => $this->maxImages,
            'notification_email' => $this->notificationEmail,
            'transparency_notice' => $this->transparencyNotice,
            'article_words' => $this->articleWords,
        ];
    }
}
