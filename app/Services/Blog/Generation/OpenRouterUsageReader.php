<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\GenerationUsage;

/**
 * Lit la consommation d'un appel dans la reponse brute d'OpenRouter (objet `usage`) : jetons, recherches web
 * et cout reel. OpenRouter renvoie le cout sans qu'on le demande (`usage.cost`, en credits, soit en
 * dollars) ; s'il manque, le cout est estime d'apres la table de tarifs et marque comme tel.
 *
 * Champs releves dans la documentation d'OpenRouter le 14/11/2026, confirmes par les essais reels du 17/11.
 */
final readonly class OpenRouterUsageReader
{
    public function __construct(private BlogAiCostEstimator $estimator) {}

    /**
     * @param  array<string, mixed>  $response  la reponse decodee du fournisseur
     * @param  string|null  $requestedModel  le modele demande, si la reponse ne dit pas lequel a servi
     */
    public function read(array $response, ?string $requestedModel = null): GenerationUsage
    {
        $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];

        $model = is_string($response['model'] ?? null) && $response['model'] !== '' ? $response['model'] : $requestedModel;
        $inputTokens = (int) ($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
        $outputTokens = (int) ($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);
        $webSearches = (int) ($usage['server_tool_use_details']['web_search_requests'] ?? 0);
        $hasRealCost = is_numeric($usage['cost'] ?? null);
        $estimated = $hasRealCost ? 0 : $this->estimator->estimateMicrousd($model, $inputTokens, $outputTokens, $webSearches);

        // Un nom date que le catalogue ne connait pas (« openai/gpt-6-luna-2026-11-01 ») compterait zero : le
        // modele demande donne alors le prix (controle du 20/11).
        if (! $hasRealCost && $estimated === 0 && $requestedModel !== null && $requestedModel !== $model && $inputTokens + $outputTokens > 0) {
            $estimated = $this->estimator->estimateMicrousd($requestedModel, $inputTokens, $outputTokens, $webSearches);
        }

        return new GenerationUsage(
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cachedTokens: (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0),
            reasoningTokens: (int) ($usage['completion_tokens_details']['reasoning_tokens'] ?? 0),
            webSearches: $webSearches,
            costMicrousd: $hasRealCost ? (int) round(((float) $usage['cost']) * 1_000_000) : $estimated,
            costIsEstimated: ! $hasRealCost,
        );
    }
}
