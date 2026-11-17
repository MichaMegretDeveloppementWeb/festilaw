<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Ai\Agents\Blog\BlogAgent;
use App\Ai\Agents\Blog\WebSearchAgent;
use App\Data\Blog\Generation\BlogAgentReply;
use App\Data\Blog\Generation\GenerationUsage;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\BlogAiCostEstimator;
use App\Services\Blog\Generation\OpenRouterUsageReader;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Throwable;

/**
 * Le seul passage des etapes editoriales vers un modele.
 *
 * La reservation existe parce qu'un seul appel peut depasser le plafond (0,1078 $ pour un essai de recherche
 * le 17/11) : verifier le plafond entre deux essais ne suffit pas. Une reponse structuree vide est la seule
 * trace d'un JSON rate, le SDK ne leve rien. Le routage au meilleur prix est demande parce qu'un meme modele
 * coute jusqu'a soixante fois plus chez certains fournisseurs. Le cout de chaque tour reste garde quand un
 * appel echoue, car il a ete facture.
 */
final readonly class BlogAgentCaller
{
    /** @param  array<string, string>  $reasoning  effort de reflexion par cle d'etape, et `default` */
    public function __construct(
        private OpenRouterUsageReader $usageReader,
        private BlogAiCostEstimator $estimator,
        private string $provider,
        private array $reasoning = [],
        private int $reservationMargin = 2,
    ) {}

    /**
     * @param  int  $timeoutSeconds  delai d'une requete au fournisseur, pas de l'appel entier
     * @param  int|null  $budgetLeftMicrousd  nul : aucun plafond
     *
     * @throws BlogAiProviderException
     */
    public function call(string $step, BlogAgent $agent, string $prompt, string $model, int $timeoutSeconds, ?int $generationId = null, ?int $budgetLeftMicrousd = null): BlogAgentReply
    {
        if ($budgetLeftMicrousd !== null) {
            $reserved = $this->reserve($agent, $prompt, $model);

            if ($reserved > $budgetLeftMicrousd) {
                throw BlogAiProviderException::overBudget($step, $reserved, max(0, $budgetLeftMicrousd));
            }
        }

        $recorder = new AgentStepRecorder;
        $agent->withRequestOptions($this->requestOptions($step, $agent));

        try {
            $response = $agent->observeStepsWith($recorder)->prompt($prompt, provider: $this->provider, model: $model, timeout: $timeoutSeconds);
        } catch (InsufficientCreditsException $e) {
            throw BlogAiProviderException::creditExhausted($e, $this->consumption($step, $agent, $generationId, $recorder, $model));
        } catch (RequestException $e) {
            $usage = $this->consumption($step, $agent, $generationId, $recorder, $model);

            if ($e->response->status() === 403) {
                throw BlogAiProviderException::creditExhausted($e, $usage);
            }

            throw BlogAiProviderException::callFailed($step, $e, $usage);
        } catch (Throwable $e) {
            throw BlogAiProviderException::callFailed($step, $e, $this->consumption($step, $agent, $generationId, $recorder, $model));
        }

        $usage = $this->consumption($step, $agent, $generationId, $recorder, $model);
        $turns = $recorder->responses();
        $cutOff = $turns !== [] && $this->finishReason($turns[array_key_last($turns)]) === FinishReason::Length->value;

        if ($agent instanceof HasStructuredOutput) {
            if ($cutOff) {
                throw BlogAiProviderException::callFailed($step, BlogEditorialException::cutOff($step, $agent->maxOutputTokens()), $usage);
            }

            if ((new BlogAgentReply($response, $usage))->structured() === []) {
                throw BlogAiProviderException::callFailed($step, BlogEditorialException::notJson($step), $usage);
            }
        }

        return new BlogAgentReply($response, $usage, $cutOff);
    }

    /**
     * OpenRouter enchaine les recherches d'un agent dans une meme requete, et sa documentation ne dit pas si
     * chaque tour relit ce qui precede : on compte le pire, chaque tour relisant tout. Trois caracteres par
     * jeton surestiment volontairement un texte anglais.
     */
    private function reserve(BlogAgent $agent, string $prompt, string $model): int
    {
        $searches = $agent instanceof WebSearchAgent ? $agent->searches() : 0;
        $perSearch = $agent instanceof WebSearchAgent ? $agent->tokensPerSearch() : 0;
        $sent = intdiv(strlen($agent->instructions()) + strlen($prompt), 3);
        $input = $sent * ($searches + 1) + intdiv($perSearch * $searches * ($searches + 1), 2);

        return $this->estimator->reserveMicrousd($model, $input, $agent->maxOutputTokens(), $searches, $this->reservationMargin);
    }

    /**
     * `require_parameters` ecarte les fournisseurs qui n'imposent pas le format JSON (Haiku chez Amazon Bedrock
     * et Claude Platform on AWS au 17/11).
     *
     * @return array<string, mixed>
     */
    private function requestOptions(string $step, BlogAgent $agent): array
    {
        $effort = $this->reasoning[$step] ?? $this->reasoning['default'] ?? null;

        return array_filter([
            'reasoning' => $effort === null ? null : ['effort' => $effort],
            'provider' => ['sort' => 'price'] + ($agent instanceof HasStructuredOutput ? ['require_parameters' => true] : []),
        ]);
    }

    /** Additionne la consommation des tours et en ecrit une ligne par tour au journal. */
    private function consumption(string $step, BlogAgent $agent, ?int $generationId, AgentStepRecorder $recorder, string $model): GenerationUsage
    {
        $total = new GenerationUsage(model: $model);

        foreach ($recorder->responses() as $response) {
            $raw = $this->rawOf($response);
            $usage = $this->usageReader->read($raw, $model);
            $total = $total->plus($usage);

            Log::info('Blog AI call.', [
                'generation_id' => $generationId,
                'step' => $step,
                'agent' => class_basename($agent),
                'model' => $usage->model,
                'provider' => is_string($raw['provider'] ?? null) ? $raw['provider'] : null,
                'openrouter_id' => is_string($raw['id'] ?? null) ? $raw['id'] : null,
                'finish_reason' => $this->finishReason($response),
                'input_tokens' => $usage->inputTokens,
                'output_tokens' => $usage->outputTokens,
                'reasoning_tokens' => $usage->reasoningTokens,
                'cached_tokens' => $usage->cachedTokens,
                'web_searches' => $usage->webSearches,
                'cost_microusd' => $usage->costMicrousd,
                'cost_is_estimated' => $usage->costIsEstimated,
            ]);
        }

        return $total;
    }

    /** Le SDK rend `unknown` pour une raison qu'il ne connait pas : celle de la reponse brute fait foi. */
    private function finishReason(StepResponse $response): string
    {
        $raw = $response->raw?->json('choices.0.finish_reason');

        return is_string($raw) && $raw !== '' ? $raw : $response->finishReason->value;
    }

    /**
     * La reponse brute du tour, ou, sans elle, ses jetons sous la meme forme : le lecteur estime alors le cout.
     *
     * @return array<string, mixed>
     */
    private function rawOf(StepResponse $response): array
    {
        $raw = $response->raw?->json();

        if (is_array($raw) && is_array($raw['usage'] ?? null)) {
            return $raw;
        }

        return [
            'model' => $response->meta->model,
            'usage' => [
                'prompt_tokens' => $response->usage->inputTokens,
                'completion_tokens' => $response->usage->outputTokens,
                'prompt_tokens_details' => ['cached_tokens' => $response->usage->cacheReadInputTokens ?? 0],
                'completion_tokens_details' => ['reasoning_tokens' => $response->usage->reasoningTokens ?? 0],
            ],
        ];
    }
}
