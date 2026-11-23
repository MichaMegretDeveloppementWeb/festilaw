<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

/**
 * Le cout des jetons d'un appel a un modele, au prix du jour de BlogAiPriceTable (cents de dollar par million de
 * jetons), plus les recherches web ; le resultat est en millioniemes de dollar. Sert au cout de repli quand le
 * fournisseur n'a pas renvoye le cout reel (un modele sans prix ne coute alors que ses recherches, et le cout
 * est de toute facon marque « estime »), a l'estimation d'un article, et a la reservation : ce qu'un appel peut
 * couter au plus, verifie contre l'arret de securite avant de l'envoyer.
 */
final readonly class BlogAiCostEstimator
{
    public function __construct(
        private BlogAiPriceTable $prices,
        private int $webSearchMicrousd,
    ) {}

    public function estimateMicrousd(?string $model, int $inputTokens, int $outputTokens, int $webSearches = 0): int
    {
        return $this->tokensMicrousd($model, $inputTokens, $outputTokens) + $webSearches * $this->webSearchMicrousd;
    }

    /**
     * Ce qu'un appel peut couter au plus, a reserver avant de l'envoyer : la part des jetons au prix reel du
     * modele, en pour cent de son prix, plus les recherches. Un modele sans prix est compte au prix du plus cher.
     */
    public function reserveMicrousd(?string $model, int $inputTokens, int $outputTokens, int $webSearches, int $marginPercent): int
    {
        $model = $this->prices->for($model) !== null ? $model : $this->mostExpensive();

        return intdiv($this->tokensMicrousd($model, $inputTokens, $outputTokens) * max(100, $marginPercent), 100) + $webSearches * $this->webSearchMicrousd;
    }

    /** Les jetons au prix du jour, un modele sans prix compte comme le plus cher. */
    public function catalogMicrousd(?string $model, int $inputTokens, int $outputTokens): int
    {
        return $this->reserveMicrousd($model, $inputTokens, $outputTokens, 0, 100);
    }

    public function webSearchesMicrousd(int $searches): int
    {
        return $searches * $this->webSearchMicrousd;
    }

    private function tokensMicrousd(?string $model, int $inputTokens, int $outputTokens): int
    {
        $price = $this->prices->for($model) ?? ['input' => 0.0, 'output' => 0.0];

        // 1 cent par million de jetons = 0,01 millionieme de dollar par jeton. Un prix du jour peut avoir des
        // decimales : arrondi par defaut, comme la division entiere des prix ronds du tableau.
        return (int) floor(($inputTokens * $price['input'] + $outputTokens * $price['output']) / 100);
    }

    private function mostExpensive(): ?string
    {
        $costs = array_map(static fn (array $price): float => $price['input'] + $price['output'], $this->prices->all());
        arsort($costs);

        return array_key_first($costs);
    }
}
