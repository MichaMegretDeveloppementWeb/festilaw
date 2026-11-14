<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

/**
 * Cout de repli d'un appel a un modele, quand le fournisseur n'a pas renvoye le cout reel : jetons multiplies
 * par la table de tarifs de config/blog.php (cents de dollar par million de jetons), plus les recherches
 * web. Calcul pur, en entiers ; le resultat est en millioniemes de dollar. Un modele absent de la table ne
 * coute que ses recherches : le cout est de toute facon marque « estime ».
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
        $price = $this->prices[(string) $model] ?? ['input' => 0, 'output' => 0];

        // 1 cent par million de jetons = 0,01 millionieme de dollar par jeton.
        $tokens = intdiv($inputTokens * (int) $price['input'] + $outputTokens * (int) $price['output'], 100);

        return $tokens + $webSearches * $this->webSearchMicrousd;
    }
}
