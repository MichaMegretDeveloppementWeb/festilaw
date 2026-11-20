<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation\Health;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Le resultat de la derniere verification quotidienne des services de la generation : quand elle a eu lieu,
 * et les problemes trouves, en messages techniques (anglais, pour le journal et le prestataire). La cliente
 * n'en voit que l'existence.
 */
final readonly class BlogGenerationHealth
{
    /** @param  list<string>  $problems */
    public function __construct(
        public CarbonImmutable $checkedAt,
        public array $problems,
    ) {}

    public function isHealthy(): bool
    {
        return $this->problems === [];
    }

    public static function fromStored(?string $stored): ?self
    {
        $data = json_decode((string) $stored, true);

        if (! is_array($data) || ! is_string($data['checked_at'] ?? null)) {
            return null;
        }

        try {
            $checkedAt = CarbonImmutable::parse($data['checked_at']);
        } catch (Throwable) {
            return null;
        }

        return new self($checkedAt, array_values(array_filter((array) ($data['problems'] ?? []), 'is_string')));
    }

    public function toStored(): string
    {
        return (string) json_encode(['checked_at' => $this->checkedAt->toIso8601String(), 'problems' => $this->problems]);
    }
}
