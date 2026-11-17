<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Ai\Agents\Blog\BlogAgent;
use App\Data\Blog\Generation\BlogAgentReply;
use App\Data\Blog\Generation\GenerationUsage;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Services\Blog\Generation\OpenRouterUsageReader;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Gateway\StepResponse;
use Throwable;

/**
 * Le seul passage des etapes editoriales vers un modele. Il fixe le fournisseur, le modele choisi dans les
 * reglages et le delai, puis additionne ce qu'ont consomme tous les tours de l'agent : le cout reel lu dans
 * la reponse brute de chacun (`usage.cost` d'OpenRouter), ou une estimation d'apres les jetons quand elle
 * manque. Une erreur du fournisseur devient une `BlogAiProviderException` qui porte ce qui a deja ete
 * facture : credit epuise (402, ou limite de la cle en 403), que le moteur ne reessaie pas, ou appel rate.
 */
final readonly class BlogAgentCaller
{
    public function __construct(
        private OpenRouterUsageReader $usageReader,
        private string $provider,
    ) {}

    /**
     * @param  string  $step  la cle de l'etape, pour le journal
     * @param  int  $timeoutSeconds  delai d'un tour (une requete au fournisseur), pas de l'appel entier
     *
     * @throws BlogAiProviderException
     */
    public function call(string $step, BlogAgent $agent, string $prompt, string $model, int $timeoutSeconds): BlogAgentReply
    {
        $recorder = new AgentStepRecorder;

        try {
            $response = $agent->observeStepsWith($recorder)->prompt($prompt, provider: $this->provider, model: $model, timeout: $timeoutSeconds);
        } catch (InsufficientCreditsException $e) {
            throw BlogAiProviderException::creditExhausted($e, $this->usage($recorder, $model));
        } catch (RequestException $e) {
            if ($e->response->status() === 403) {
                throw BlogAiProviderException::creditExhausted($e, $this->usage($recorder, $model));
            }

            throw BlogAiProviderException::callFailed($step, $e, $this->usage($recorder, $model));
        } catch (Throwable $e) {
            throw BlogAiProviderException::callFailed($step, $e, $this->usage($recorder, $model));
        }

        return new BlogAgentReply($response, $this->usage($recorder, $model));
    }

    /** La consommation de tous les tours releves, chacun lu dans sa reponse brute, ou estime sans elle. */
    private function usage(AgentStepRecorder $recorder, string $model): GenerationUsage
    {
        return array_reduce(
            $recorder->responses(),
            fn (GenerationUsage $total, StepResponse $response): GenerationUsage => $total->plus($this->usageReader->read($this->rawOf($response), $model)),
            new GenerationUsage(model: $model),
        );
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
