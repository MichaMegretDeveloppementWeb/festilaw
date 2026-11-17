<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Les regles de longueur du texte d'un article (`blog.ai.article_length`) : les bornes du reglage, l'ecart
 * demande au modele et l'ecart que la redaction accepte avant de demander une correction. Les consignes
 * seules ne tiennent pas la longueur : au premier article reel, 600 a 800 mots demandes, 522 ecrits.
 */
final readonly class ArticleLengthRules
{
    public function __construct(
        public int $minWords,
        public int $maxWords,
        public int $askedTolerancePercent,
        public int $acceptedTolerancePercent,
    ) {}

    /** @param  array<string, int>  $config */
    public static function fromConfig(array $config): self
    {
        return new self(
            minWords: (int) ($config['min_words'] ?? 400),
            maxWords: (int) ($config['max_words'] ?? 2_000),
            askedTolerancePercent: (int) ($config['asked_tolerance_percent'] ?? 10),
            acceptedTolerancePercent: (int) ($config['accepted_tolerance_percent'] ?? 20),
        );
    }

    public function clamp(int $words): int
    {
        return min($this->maxWords, max($this->minWords, $words));
    }

    public function for(int $words): GenerationLength
    {
        $words = $this->clamp($words);

        return new GenerationLength(
            words: $words,
            askedMin: $this->below($words, $this->askedTolerancePercent),
            askedMax: $this->above($words, $this->askedTolerancePercent),
            acceptedMin: $this->below($words, $this->acceptedTolerancePercent),
            acceptedMax: $this->above($words, $this->acceptedTolerancePercent),
        );
    }

    private function below(int $words, int $percent): int
    {
        return intdiv($words * (100 - $percent), 100);
    }

    private function above(int $words, int $percent): int
    {
        return intdiv($words * (100 + $percent) + 99, 100);
    }
}
