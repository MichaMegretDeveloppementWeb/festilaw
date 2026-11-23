<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Repositories\Blog\BlogGenerationHealthRepository;
use Carbon\CarbonImmutable;

/**
 * Le prix de chaque modele de texte du catalogue, en cents de dollar par million de jetons lus et ecrits
 * (decision du 23/11) : celui que la verification du matin a releve chez le fournisseur le moins cher, quand il
 * a moins de `prices_valid_days` jours, et sinon celui du tableau de config/blog.php, qui ne sert plus que de
 * secours. Un prix nul ou absent ne remplace jamais celui du tableau, et un modele hors du catalogue n'a pas de
 * prix.
 *
 * L'estimation, l'arret de securite et le cout de repli y lisent leurs prix. La lecture (une requete) n'a lieu
 * qu'au premier prix demande, une fois par requete ou par commande (liaison `scoped`).
 */
final class BlogAiPriceTable
{
    /** @var array<string, array{input: float, output: float}>|null */
    private ?array $prices = null;

    /**
     * @param  array<string, array{input?: int|float, output?: int|float}>  $catalog  blog.ai.models
     */
    public function __construct(
        private readonly ?BlogGenerationHealthRepository $health,
        private readonly array $catalog,
        private readonly int $validDays,
    ) {}

    /**
     * Les seuls prix du tableau, sans releve : pour un calcul qui ne doit dependre que de ses donnees.
     *
     * @param  array<string, array{input?: int|float, output?: int|float}>  $catalog
     */
    public static function fixed(array $catalog): self
    {
        return new self(null, $catalog, 0);
    }

    /** @return array<string, array{input: float, output: float}> */
    public function all(): array
    {
        return $this->prices ??= $this->load();
    }

    /** @return array{input: float, output: float}|null */
    public function for(?string $modelId): ?array
    {
        return $this->all()[(string) $modelId] ?? null;
    }

    /** @return array<string, array{input: float, output: float}> */
    private function load(): array
    {
        $prices = [];

        foreach ($this->catalog as $modelId => $model) {
            if (isset($model['input'], $model['output'])) {
                $prices[(string) $modelId] = ['input' => (float) $model['input'], 'output' => (float) $model['output']];
            }
        }

        $health = $this->health?->latest();

        if ($health === null || $health->checkedAt->lessThan(CarbonImmutable::now()->subDays($this->validDays))) {
            return $prices;
        }

        foreach ($health->prices as $modelId => $price) {
            if (isset($prices[$modelId]) && $price['input'] > 0 && $price['output'] > 0) {
                $prices[$modelId] = $price;
            }
        }

        return $prices;
    }
}
