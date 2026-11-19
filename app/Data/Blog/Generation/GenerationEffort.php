<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogEffortLevel;

/**
 * Le niveau d'effort d'UNE generation, ecrit dans son `payload` comme ses images et sa longueur : choisi au
 * lancement, ou repris des reglages pour une generation automatique. Le pipeline ne lit que la generation,
 * jamais les reglages du moment, et une relance garde donc le meme niveau.
 */
final class GenerationEffort
{
    public const PAYLOAD_KEY = 'effort';

    /**
     * Ce qui a ete ecrit sur la generation, ou nul pour une generation anterieure a ce choix.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromPayload(?array $payload): ?BlogEffortLevel
    {
        return BlogEffortLevel::tryFrom((string) ($payload[self::PAYLOAD_KEY] ?? ''));
    }

    /**
     * Le niveau avec lequel une generation tourne : le sien, ou Moyen pour une generation anterieure a ce choix,
     * qui a tourne avec le reglage qu'est devenu ce niveau.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function forRun(?array $payload): BlogEffortLevel
    {
        return self::fromPayload($payload) ?? BlogEffortLevel::Medium;
    }
}
