<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation\Health;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Le resultat de la derniere verification quotidienne des services de la generation : quand elle a eu lieu,
 * les problemes trouves, en messages techniques (anglais, pour le journal et le prestataire), et les prix du
 * jour de chaque modele de texte du catalogue chez son fournisseur le moins cher (cents de dollar par million
 * de jetons), que l'estimation emploie (decision du 23/11). La cliente ne voit que l'existence d'un probleme.
 */
final readonly class BlogGenerationHealth
{
    /**
     * @param  list<string>  $problems
     * @param  array<string, array{input: float, output: float}>  $prices
     */
    public function __construct(
        public CarbonImmutable $checkedAt,
        public array $problems,
        public array $prices = [],
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

        $prices = [];

        foreach ((array) ($data['prices'] ?? []) as $modelId => $price) {
            if (is_string($modelId) && is_numeric($price['input'] ?? null) && is_numeric($price['output'] ?? null)) {
                $prices[$modelId] = ['input' => (float) $price['input'], 'output' => (float) $price['output']];
            }
        }

        return new self($checkedAt, array_values(array_filter((array) ($data['problems'] ?? []), 'is_string')), $prices);
    }

    public function toStored(): string
    {
        return (string) json_encode([
            'checked_at' => $this->checkedAt->toIso8601String(),
            'problems' => $this->problems,
            'prices' => $this->prices,
        ]);
    }
}
