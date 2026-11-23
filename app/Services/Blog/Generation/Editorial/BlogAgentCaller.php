<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Ai\Agents\Blog\BlogAgent;
use App\Ai\Agents\Blog\WebSearchAgent;
use App\Data\Blog\Generation\BlogAgentReply;
use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogAiFailureKind;
use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogEditorialException;
use App\Repositories\Blog\BlogGenerationCancelRepository;
use App\Services\Blog\Generation\BlogAiCostEstimator;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogEffortLevels;
use App\Services\Blog\Generation\OpenRouterKeyActivator;
use App\Services\Blog\Generation\OpenRouterUsageReader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Files\File;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Throwable;

/**
 * Le seul passage des etapes editoriales vers un modele.
 *
 * La reservation existe parce qu'un seul appel peut couter beaucoup (0,1078 $ pour un essai de recherche
 * le 17/11) : verifier l'arret de securite entre deux essais ne suffit pas. Elle compte la reponse la plus longue
 * permise au prix mesure du modele, ou a la marge d'un modele jamais mesure : la triple marge d'avant (prix de
 * DeepSeek chez Wafer le 17/11) reservait 0,33 $ pour une verification de GPT-6.1 Terra qui en coute 0,016, et
 * l'arret de securite d'un article ordinaire ne l'aurait jamais laissee partir (banc du 18/11). Une reponse structuree vide est la seule
 * trace d'un JSON rate, le SDK ne leve rien. Le routage au meilleur prix est demande parce qu'un meme modele
 * coute jusqu'a soixante fois plus chez certains fournisseurs. Le cout de chaque tour reste garde quand un
 * appel echoue, car il a ete facture.
 */
final readonly class BlogAgentCaller
{
    /**
     * Jetons comptes pour une image jointe : une photo de 1 280 pixels en coute environ 1 500 chez Anthropic
     * (largeur fois hauteur divisees par 750), un peu moins chez OpenAI.
     */
    private const TOKENS_PER_IMAGE = 1_600;

    public function __construct(
        private OpenRouterUsageReader $usageReader,
        private BlogAiCostEstimator $estimator,
        private string $provider,
        private BlogAiModelCatalog $models,
        private BlogEffortLevels $effort,
        private BlogGenerationCancelRepository $cancellations,
        private OpenRouterKeyActivator $key,
        private int $defaultMarginPercent = 150,
    ) {}

    /**
     * @param  int  $timeoutSeconds  delai d'une requete au fournisseur, pas de l'appel entier
     * @param  int|null  $budgetLeftMicrousd  nul : aucun arret de securite
     * @param  BlogEffortLevel  $effort  le niveau d'effort de la generation, qui fixe la reflexion demandee
     * @param  list<File>  $attachments  pieces jointes, comme la photo que le controle des photos regarde
     *
     * @throws BlogAiProviderException
     */
    public function call(string $step, BlogAgent $agent, string $prompt, string $model, int $timeoutSeconds, ?int $generationId = null, ?int $budgetLeftMicrousd = null, BlogEffortLevel $effort = BlogEffortLevel::Medium, array $attachments = []): BlogAgentReply
    {
        if ($generationId !== null && $this->cancellations->isRequested($generationId)) {
            throw BlogAiProviderException::cancelled($step);
        }

        $this->key->activate();

        try {
            return $this->send($step, $agent, $prompt, $model, $timeoutSeconds, $generationId, $budgetLeftMicrousd, $effort, $attachments);
        } catch (BlogAiProviderException $e) {
            $lower = $effort->lower();

            if ($e->kind !== BlogAiFailureKind::CutOff || $lower === null) {
                throw $e;
            }

            // Une reponse coupee a sa limite le serait encore a l'identique : la reprise demande moins de
            // reflexion, qui prend sa part des memes jetons (controle du 20/11). Une seule reprise.
            Log::warning('Blog AI answer cut off at its limit, asked again with less reasoning.', ['generation_id' => $generationId, 'step' => $step, 'model' => $model, 'effort' => $lower->value]);
            $spent = $e->usage ?? GenerationUsage::none();
            $budgetLeft = $budgetLeftMicrousd === null ? null : $budgetLeftMicrousd - $spent->costMicrousd;

            try {
                return $this->send($step, $agent, $prompt, $model, $timeoutSeconds, $generationId, $budgetLeft, $lower, $attachments)->withEarlierUsage($spent);
            } catch (BlogAiProviderException $retry) {
                throw $retry->withEarlierUsage($spent);
            }
        }
    }

    /**
     * Un appel, et la sorte de son echec : la cle refusee, un modele retire, un refus du fournisseur et une
     * reponse coupee ne se reessaient pas comme une panne passagere (controle du 20/11).
     *
     * @param  list<File>  $attachments
     *
     * @throws BlogAiProviderException
     */
    private function send(string $step, BlogAgent $agent, string $prompt, string $model, int $timeoutSeconds, ?int $generationId, ?int $budgetLeftMicrousd, BlogEffortLevel $effort, array $attachments): BlogAgentReply
    {
        if ($budgetLeftMicrousd !== null) {
            $reserved = $this->reserve($agent, $prompt, $model, count($attachments));

            if ($reserved > $budgetLeftMicrousd) {
                throw BlogAiProviderException::overBudget($step, $reserved, max(0, $budgetLeftMicrousd));
            }
        }

        $recorder = new AgentStepRecorder;
        $agent->withRequestOptions($this->requestOptions($step, $agent, $model, $effort));

        try {
            $response = $agent->observeStepsWith($recorder)->prompt($prompt, $attachments, provider: $this->provider, model: $model, timeout: $timeoutSeconds);
        } catch (InsufficientCreditsException $e) {
            throw BlogAiProviderException::creditExhausted($e, $this->consumption($step, $agent, $generationId, $recorder, $model));
        } catch (RateLimitedException|ProviderOverloadedException|ProviderConnectionException|ConnectionException $e) {
            throw BlogAiProviderException::callFailed($step, $e, $this->consumption($step, $agent, $generationId, $recorder, $model), BlogAiFailureKind::Transient);
        } catch (RequestException $e) {
            throw $this->classified($step, $model, $e, $this->consumption($step, $agent, $generationId, $recorder, $model));
        } catch (AiException $e) {
            // Une erreur dans une reponse 200 (« OpenRouter Error: ... ») : le fournisseur a flanche en route.
            throw BlogAiProviderException::callFailed($step, $e, $this->consumption($step, $agent, $generationId, $recorder, $model), BlogAiFailureKind::Transient);
        } catch (Throwable $e) {
            throw BlogAiProviderException::callFailed($step, $e, $this->consumption($step, $agent, $generationId, $recorder, $model));
        }

        $usage = $this->consumption($step, $agent, $generationId, $recorder, $model);
        $turns = $recorder->responses();
        $cutOff = $turns !== [] && $this->finishReason($turns[array_key_last($turns)]) === FinishReason::Length->value;

        if ($agent instanceof HasStructuredOutput) {
            if ($cutOff) {
                throw BlogAiProviderException::callFailed($step, BlogEditorialException::cutOff($step, $agent->maxOutputTokens()), $usage, BlogAiFailureKind::CutOff);
            }

            if ((new BlogAgentReply($response, $usage))->structured() === []) {
                throw BlogAiProviderException::callFailed($step, BlogEditorialException::notJson($step), $usage);
            }
        }

        return new BlogAgentReply($response, $usage, $cutOff);
    }

    /**
     * La sorte d'un refus HTTP que le SDK n'a pas deja classe. OpenRouter renvoie 401 pour une cle refusee, 400
     * ou 404 pour un modele inconnu ou que plus aucun fournisseur ne sert avec les parametres demandes, 403
     * pour une limite de cle atteinte comme pour un texte refuse par sa moderation.
     */
    private function classified(string $step, string $model, RequestException $e, GenerationUsage $usage): BlogAiProviderException
    {
        $status = $e->response->status();
        $message = $e->response->json('error.message');
        $message = is_string($message) ? $message : '';

        return match (true) {
            $status === 401 => BlogAiProviderException::keyRejected($step, $e, $usage),
            $status === 403 && preg_match('/limit|credit/i', $message) === 1 => BlogAiProviderException::creditExhausted($e, $usage),
            $status === 403 => BlogAiProviderException::callFailed($step, $e, $usage, BlogAiFailureKind::Refused),
            in_array($status, [400, 404], true) && preg_match('/model|endpoint/i', $message) === 1 => BlogAiProviderException::modelUnavailable($step, $model, $e, $usage),
            $status === 408 || $status === 429 || $status >= 500 => BlogAiProviderException::callFailed($step, $e, $usage, BlogAiFailureKind::Transient),
            default => BlogAiProviderException::callFailed($step, $e, $usage),
        };
    }

    /**
     * OpenRouter enchaine les recherches d'un agent dans une meme requete, et sa documentation ne dit pas si
     * chaque tour relit ce qui precede : on compte le pire, chaque tour relisant tout. Trois caracteres par
     * jeton surestiment volontairement un texte anglais. Une image jointe compte un nombre fixe de jetons.
     */
    private function reserve(BlogAgent $agent, string $prompt, string $model, int $images = 0): int
    {
        $searches = $agent instanceof WebSearchAgent ? $agent->searches() : 0;
        $perSearch = $agent instanceof WebSearchAgent ? $agent->tokensPerSearch() : 0;
        $sent = intdiv(strlen($agent->instructions()) + strlen($prompt), 3) + $images * self::TOKENS_PER_IMAGE;
        $input = $sent * ($searches + 1) + intdiv($perSearch * $searches * ($searches + 1), 2);

        return $this->estimator->reserveMicrousd($model, $input, $agent->maxOutputTokens(), $searches, $this->models->priceMarginPercent($model) ?? $this->defaultMarginPercent);
    }

    /**
     * `require_parameters` ecarte les fournisseurs qui n'imposent pas le format JSON (Haiku chez Amazon Bedrock
     * et Claude Platform on AWS au 17/11).
     *
     * @return array<string, mixed>
     */
    private function requestOptions(string $step, BlogAgent $agent, string $model, BlogEffortLevel $level): array
    {
        return array_filter([
            'reasoning' => ['effort' => $this->effort->reasoning($level, $step, $model)],
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
