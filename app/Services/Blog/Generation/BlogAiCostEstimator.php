<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

/**
 * Cout de repli d'un appel a un modele, quand le fournisseur n'a pas renvoye le cout reel : jetons multiplies
 * par la table de tarifs de config/blog.php (cents de dollar par million de jetons), plus les recherches
 * web. Calcul pur, en entiers ; le resultat est en millioniemes de dollar. Un modele absent de la table ne
 * coute que ses recherches : le cout est de toute facon marque « estime ». Sert aussi a la reservation : ce
 * qu'un appel peut couter au plus, verifie contre le plafond avant de l'envoyer.
 */
final readonly class BlogAiCostEstimator
{
    /** @param  array<string, array{input: int, output: int}>  $prices */
    public function __construct(
        private array $prices,
        private int $webSearchMicrousd,
    ) {}

    public function estimateMicrousd(?string $model, int $inputTokens, int $outputTokens, int $webSearches = 0): int
    {
        return $this->tokensMicrousd($model, $inputTokens, $outputTokens) + $webSearches * $this->webSearchMicrousd;
    }

    /**
     * Ce qu'un appel peut couter au plus, a reserver avant de l'envoyer : la part des jetons multipliee par une
     * marge (le prix change selon le fournisseur qui repond), plus les recherches. Un modele absent de la
     * table est compte au prix du plus cher de la table.
     */
    public function reserveMicrousd(?string $model, int $inputTokens, int $outputTokens, int $webSearches, int $margin): int
    {
        $model = isset($this->prices[(string) $model]) ? $model : $this->mostExpensive();

        return $this->tokensMicrousd($model, $inputTokens, $outputTokens) * max(1, $margin) + $webSearches * $this->webSearchMicrousd;
    }

    /** Les jetons au prix du catalogue, un modele absent de la table compte comme le plus cher. */
    public function catalogMicrousd(?string $model, int $inputTokens, int $outputTokens): int
    {
        return $this->reserveMicrousd($model, $inputTokens, $outputTokens, 0, 1);
    }

    public function webSearchesMicrousd(int $searches): int
    {
        return $searches * $this->webSearchMicrousd;
    }

    private function tokensMicrousd(?string $model, int $inputTokens, int $outputTokens): int
    {
        $price = $this->prices[(string) $model] ?? ['input' => 0, 'output' => 0];

        // 1 cent par million de jetons = 0,01 millionieme de dollar par jeton.
        return intdiv($inputTokens * (int) $price['input'] + $outputTokens * (int) $price['output'], 100);
    }

    private function mostExpensive(): ?string
    {
        $costs = array_map(static fn (array $price): int => (int) $price['input'] + (int) $price['output'], $this->prices);
        arsort($costs);

        return array_key_first($costs);
    }
}
