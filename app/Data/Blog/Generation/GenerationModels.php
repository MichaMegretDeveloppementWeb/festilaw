<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Les modeles d'UNE generation, ecrits dans son `payload` a son lancement comme ses images, sa longueur et son
 * effort (controle du 20/11) : changer les reglages pendant qu'elle tourne ne change plus ses modeles, alors que
 * son arret de securite, lui, est fige depuis le 18/11. Une generation anterieure garde les reglages du moment.
 */
final class GenerationModels
{
    public const PAYLOAD_KEY = 'models';

    /** @return array<string, string> */
    public static function payloadFor(BlogAiSettings $settings): array
    {
        return $settings->models;
    }

    /**
     * Les reglages avec lesquels la generation tourne : ceux du moment, avec ses propres modeles.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function applyTo(BlogAiSettings $settings, ?array $payload): BlogAiSettings
    {
        $models = array_filter((array) ($payload[self::PAYLOAD_KEY] ?? []), static fn (mixed $model): bool => is_string($model) && $model !== '');

        return $models === [] ? $settings : $settings->withModels($models);
    }
}
