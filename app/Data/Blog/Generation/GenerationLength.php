<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * La longueur du texte d'UNE generation, en mots du texte anglais, avec l'ecart demande au modele et l'ecart
 * accepte. Seul le nombre de mots est ecrit dans `payload` : les ecarts se recalculent par ArticleLengthRules,
 * comme les images (GenerationImageOptions), pour que le pipeline ne lise que la generation.
 */
final readonly class GenerationLength
{
    public const PAYLOAD_KEY = 'length';

    public function __construct(
        public int $words,
        public int $askedMin,
        public int $askedMax,
        public int $acceptedMin,
        public int $acceptedMax,
    ) {}

    /**
     * Le nombre de mots ecrit sur la generation, ou nul pour une generation anterieure a ce choix.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function wordsFromPayload(?array $payload): ?int
    {
        $words = $payload[self::PAYLOAD_KEY]['words'] ?? null;

        return is_numeric($words) ? (int) $words : null;
    }

    /** @return array{words: int} */
    public function toPayload(): array
    {
        return self::payloadFor($this->words);
    }

    /** @return array{words: int} */
    public static function payloadFor(int $words): array
    {
        return ['words' => $words];
    }

    public function accepts(int $words): bool
    {
        return $words >= $this->acceptedMin && $words <= $this->acceptedMax;
    }
}
