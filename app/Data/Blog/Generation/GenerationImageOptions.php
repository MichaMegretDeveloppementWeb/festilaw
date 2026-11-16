<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogAiImageMode;

/**
 * Les images d'UNE generation : d'ou elles viennent et combien l'article peut en porter au plus (l'image
 * principale comprise). Choisies au lancement, ou reprises des reglages pour une generation automatique,
 * elles sont ecrites dans `payload` de la generation : le pipeline ne lit que la generation, jamais les
 * reglages du moment.
 */
final readonly class GenerationImageOptions
{
    public const PAYLOAD_KEY = 'images';

    public function __construct(
        public BlogAiImageMode $mode,
        public int $maxImages,
    ) {}

    /** Les valeurs par defaut des reglages : celles d'une generation automatique. */
    public static function fromSettings(BlogAiSettings $settings): self
    {
        return new self($settings->imageMode, $settings->maxImages);
    }

    /**
     * Ce qui a ete ecrit sur la generation, ou nul pour une generation anterieure a ce choix.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromPayload(?array $payload): ?self
    {
        $stored = $payload[self::PAYLOAD_KEY] ?? null;
        $mode = is_array($stored) ? BlogAiImageMode::tryFrom((string) ($stored['mode'] ?? '')) : null;

        if ($mode === null) {
            return null;
        }

        return new self($mode, min(BlogAiSettings::MAX_IMAGES, max(1, (int) ($stored['max'] ?? 1))));
    }

    /** @return array{mode: string, max: int} */
    public function toPayload(): array
    {
        return ['mode' => $this->mode->value, 'max' => $this->maxImages];
    }
}
