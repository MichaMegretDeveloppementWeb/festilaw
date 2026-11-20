<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Health;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Exceptions\Blog\BlogEditorialException;
use App\Exceptions\Blog\BlogHealthCheckException;
use App\Repositories\Blog\BlogGenerationProgressRepository;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\Editorial\Images\PixabayStockImageProvider;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * La verification quotidienne des services de la generation (controle du 20/11), sans aucun appel a un
 * modele : ce qui l'empecherait de tourner des annees sans intervention, vu avant qu'une generation n'echoue.
 *
 * - La reference des regles europeennes a-t-elle passe sa date de relecture ?
 * - La file des generations a-t-elle cesse d'avancer ?
 * - Chez OpenRouter : la cle est-elle acceptee, sa limite approche-t-elle, chaque modele choisi est-il encore
 *   propose, par un fournisseur qui accepte la sortie structuree et la reflexion, a un prix proche du
 *   catalogue, et le modele du sujet lit-il les images (controle des photos) ? Les modeles du catalogue sont-ils
 *   encore listes, et l'un d'eux va-t-il etre retire ?
 * - Pixabay repond-il avec la cle du site ?
 *
 * Rend les problemes en messages techniques (anglais), un par probleme. Une panne d'un service pendant la
 * verification est elle-meme un probleme : la verification ne leve jamais.
 */
final readonly class BlogGenerationHealthCheck
{
    /**
     * @param  array<string, array{input: int|float, output: int|float}>  $textCatalog  blog.ai.models
     * @param  array<string, mixed>  $imageCatalog  blog.ai.image_models
     * @param  array{price_drift_percent: int, key_remaining_percent: int, expiry_warning_days: int, stalled_after_minutes: int}  $thresholds  blog.ai.health
     * @param  array{checked_on: string, review_by: string}  $rulesReference  blog.ai.rules_reference
     */
    public function __construct(
        private OpenRouterCatalogReader $openRouter,
        private PixabayStockImageProvider $pixabay,
        private BlogGenerationProgressRepository $progress,
        private BlogAiSettingsService $settings,
        private ?string $openRouterKey,
        private string $pipeline,
        private array $textCatalog,
        private array $imageCatalog,
        private array $thresholds,
        private array $rulesReference,
    ) {}

    /** @return list<string> */
    public function problems(): array
    {
        $now = CarbonImmutable::now();
        $problems = [...$this->rulesReference($now), ...$this->queue($now)];

        if ($this->pipeline !== 'editorial') {
            return $problems;
        }

        return [...$problems, ...$this->openRouter($now), ...$this->pixabay()];
    }

    /** @return list<string> */
    private function rulesReference(CarbonImmutable $now): array
    {
        $reviewBy = CarbonImmutable::parse($this->rulesReference['review_by'])->startOfDay();

        return $now->startOfDay()->greaterThan($reviewBy)
            ? ["The EU rules reference (resources/prompts/blog/eu-product-rules.md, checked on {$this->rulesReference['checked_on']}) was due for review on {$this->rulesReference['review_by']}."]
            : [];
    }

    /** @return list<string> */
    private function queue(CarbonImmutable $now): array
    {
        $lastMove = $this->progress->lastActiveMoveAt();
        $minutes = $this->thresholds['stalled_after_minutes'];

        return $lastMove !== null && $lastMove->lessThan($now->subMinutes($minutes))
            ? ["The blog generation queue has not moved since {$lastMove->toDateTimeString()} UTC (more than {$minutes} minutes)."]
            : [];
    }

    /** @return list<string> */
    private function openRouter(CarbonImmutable $now): array
    {
        if (blank($this->openRouterKey)) {
            return ['OPENROUTER_API_KEY is empty: no model can be called.'];
        }

        try {
            $problems = $this->key();
            $settings = $this->settings->current();

            return [...$problems, ...$this->chosenTextModels($settings), ...$this->listedModels($settings, $now)];
        } catch (BlogHealthCheckException $e) {
            return [...($problems ?? []), $e->getMessage()];
        }
    }

    /**
     * @return list<string>
     *
     * @throws BlogHealthCheckException
     */
    private function key(): array
    {
        $key = $this->openRouter->keyStatus();

        if ($key->limit === null || $key->remaining === null || $key->remaining >= $key->limit * $this->thresholds['key_remaining_percent'] / 100) {
            return [];
        }

        return [sprintf('The OpenRouter key has %.2f $ left of its %.2f $ limit.', $key->remaining, $key->limit)];
    }

    /**
     * Chaque modele de texte choisi, une fois meme s'il sert a plusieurs etapes.
     *
     * @return list<string>
     *
     * @throws BlogHealthCheckException
     */
    private function chosenTextModels(BlogAiSettings $settings): array
    {
        $roles = [];

        foreach ($settings->models as $role => $modelId) {
            if ($role !== BlogAiModelCatalog::IMAGE_SETTING) {
                $roles[$modelId][] = $role;
            }
        }

        $problems = [];

        foreach ($roles as $modelId => $modelRoles) {
            $usedFor = implode(', ', $modelRoles);
            $offer = $this->openRouter->offer($modelId);

            if ($offer === null) {
                $problems[] = "Model [{$modelId}] chosen for [{$usedFor}] is no longer offered by OpenRouter.";

                continue;
            }

            if (! $offer->hasEligibleProvider) {
                $problems[] = "No OpenRouter provider of [{$modelId}] (chosen for [{$usedFor}]) accepts structured outputs with reasoning.";
            }

            if (in_array('topic', $modelRoles, true) && ! $offer->acceptsImages) {
                $problems[] = "Model [{$modelId}] chosen for [topic] does not read images, so stock photos cannot be checked.";
            }

            $catalog = $this->textCatalog[$modelId] ?? null;
            $drift = 1 + $this->thresholds['price_drift_percent'] / 100;

            if ($catalog !== null && $offer->inputCentsPerMillion !== null && $offer->outputCentsPerMillion !== null
                && ($offer->inputCentsPerMillion > $catalog['input'] * $drift || $offer->outputCentsPerMillion > $catalog['output'] * $drift)) {
                $problems[] = sprintf(
                    'The cheapest provider of [%s] now charges %s/%s cents per million tokens, the catalog (config/blog.php) says %s/%s.',
                    $modelId, $offer->inputCentsPerMillion, $offer->outputCentsPerMillion, $catalog['input'], $catalog['output'],
                );
            }
        }

        return $problems;
    }

    /**
     * Les modeles du catalogue, et le modele d'images choisi, encore listes par OpenRouter ; ceux qu'il va retirer.
     *
     * @return list<string>
     *
     * @throws BlogHealthCheckException
     */
    private function listedModels(BlogAiSettings $settings, CarbonImmutable $now): array
    {
        $problems = [];
        $soon = $now->addDays($this->thresholds['expiry_warning_days'])->toDateString();
        $images = $this->openRouter->listedModels(imageOutput: true);
        $chosenImage = (string) $settings->modelFor(BlogAiModelCatalog::IMAGE_SETTING);

        if ($chosenImage !== '' && ! array_key_exists($chosenImage, $images)) {
            $problems[] = "Image model [{$chosenImage}] chosen for the images is no longer offered by OpenRouter.";
        }

        foreach ([[array_keys($this->textCatalog), $this->openRouter->listedModels(imageOutput: false)], [array_keys($this->imageCatalog), $images]] as [$catalogIds, $listed]) {
            foreach ($catalogIds as $modelId) {
                if (! array_key_exists($modelId, $listed)) {
                    $problems[] = "Catalog model [{$modelId}] is no longer listed by OpenRouter.";
                } elseif ($listed[$modelId] !== null && $listed[$modelId] <= $soon) {
                    $problems[] = "Model [{$modelId}] of the catalog will be retired by OpenRouter on {$listed[$modelId]}.";
                }
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function pixabay(): array
    {
        try {
            $this->pixabay->probe();

            return [];
        } catch (BlogEditorialException $e) {
            return [$e->getMessage()];
        } catch (Throwable $e) {
            return ['Blog image source failed: '.$e::class];
        }
    }
}
