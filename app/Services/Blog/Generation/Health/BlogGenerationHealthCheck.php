<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Health;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\Health\BlogGenerationHealth;
use App\Enums\Blog\Generation\OpenRouterKeyCheck;
use App\Exceptions\Blog\BlogEditorialException;
use App\Exceptions\Blog\BlogHealthCheckException;
use App\Repositories\Blog\BlogGenerationProgressRepository;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationBudget;
use App\Services\Blog\Generation\BlogGenerationCalendar;
use App\Services\Blog\Generation\BlogGenerationPlannerHeartbeat;
use App\Services\Blog\Generation\Editorial\Images\PixabayStockImageProvider;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * La verification quotidienne des services de la generation (controle du 20/11), sans aucun appel a un
 * modele : ce qui l'empecherait de tourner des annees sans intervention, vu avant qu'une generation n'echoue.
 *
 * - La reference des regles europeennes a-t-elle passe sa date de relecture ?
 * - La file des generations a-t-elle cesse d'avancer ?
 * - Generation automatique activee : la planification des lots tourne-t-elle encore chaque heure, et un lot du
 *   depuis plus de deux heures est-il parti (constat du 26/11 : la planification n'avait jamais tourne en
 *   production, sans que rien le signale) ? Un lot retenu par la pause des brouillons n'est pas en retard.
 * - Chez OpenRouter : la cle des reglages est-elle acceptee, expire-t-elle bientot (14 jours), sa limite
 *   approche-t-elle, chaque modele choisi est-il encore
 *   propose, par un fournisseur qui accepte la sortie structuree et la reflexion, et le modele du sujet lit-il
 *   les images (controle des photos) ? Les modeles du catalogue sont-ils encore listes, et l'un d'eux va-t-il
 *   etre retire ?
 * - Pixabay repond-il avec la cle du site ?
 *
 * Elle releve aussi le prix du jour de chaque modele de texte du catalogue chez son fournisseur le moins cher,
 * que l'estimation emploie a la place du tableau de config/blog.php (decision du 23/11).
 *
 * Rend les problemes en messages techniques (anglais), un par probleme. Une panne d'un service pendant la
 * verification est elle-meme un probleme : la verification ne leve jamais.
 */
final readonly class BlogGenerationHealthCheck
{
    /** Un prix du jour plus de 5 fois plus haut ou plus bas que le tableau n'est pas cru. */
    private const PLAUSIBLE_PRICE_FACTOR = 5;

    /**
     * @param  array<string, array{input: int|float, output: int|float}>  $textCatalog  blog.ai.models
     * @param  array<string, mixed>  $imageCatalog  blog.ai.image_models
     * @param  ?string  $openRouterKey  la cle enregistree dans les reglages, nulle s'il n'y en a pas
     * @param  array{key_remaining_percent: int, key_expiry_warning_days: int, expiry_warning_days: int, stalled_after_minutes: int, planner_late_after_minutes: int}  $thresholds  blog.ai.health
     * @param  array{checked_on: string, review_by: string}  $rulesReference  blog.ai.rules_reference
     */
    public function __construct(
        private OpenRouterCatalogReader $openRouter,
        private PixabayStockImageProvider $pixabay,
        private BlogGenerationProgressRepository $progress,
        private BlogAiSettingsService $settings,
        private BlogGenerationCalendar $calendar,
        private BlogGenerationPlannerHeartbeat $plannerHeartbeat,
        private BlogGenerationBudget $budget,
        private ?string $openRouterKey,
        private string $pipeline,
        private array $textCatalog,
        private array $imageCatalog,
        private array $thresholds,
        private array $rulesReference,
    ) {}

    public function run(): BlogGenerationHealth
    {
        $now = CarbonImmutable::now();
        $problems = [...$this->rulesReference($now), ...$this->queue($now), ...$this->automatic($now)];
        $prices = [];
        $key = null;
        $keyExpiresAt = null;

        if ($this->pipeline === 'editorial') {
            $problems = [...$problems, ...$this->openRouter($now, $prices, $key, $keyExpiresAt), ...$this->pixabay()];
        }

        return new BlogGenerationHealth($now, $problems, $prices, $key, $keyExpiresAt);
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
    private function automatic(CarbonImmutable $now): array
    {
        $settings = $this->settings->current();

        if (! $settings->enabled) {
            return [];
        }

        $minutes = $this->thresholds['planner_late_after_minutes'];
        $problems = [];
        $lastRun = $this->plannerHeartbeat->lastRunAt();

        if ($lastRun !== null && $lastRun->lessThan($now->subMinutes($minutes))) {
            $problems[] = "The automatic generation planner (festilaw:blog-generation-schedule, every hour) has not run since {$lastRun->toDateTimeString()} UTC (more than {$minutes} minutes).";
        }

        $late = $this->calendar->lateSlot($settings, $now, $minutes);

        if ($late !== null && $this->budget->remainingDraftRoom($settings) > 0) {
            $problems[] = "The automatic generation batch due at {$late->toDateTimeString()} UTC has not been queued (more than {$minutes} minutes late).";
        }

        return $problems;
    }

    /**
     * @param  array<string, array{input: float, output: float}>  $prices  rempli au fil des modeles lus, meme si la
     *                                                                     lecture s'arrete en route
     * @return list<string>
     */
    private function openRouter(CarbonImmutable $now, array &$prices, ?OpenRouterKeyCheck &$key, ?CarbonImmutable &$keyExpiresAt): array
    {
        if (blank($this->openRouterKey)) {
            $key = OpenRouterKeyCheck::Missing;

            return ['No OpenRouter key is saved in the generation settings: no model can be called.'];
        }

        try {
            $problems = $this->key($now, $key, $keyExpiresAt);
            $settings = $this->settings->current();

            return [...$problems, ...$this->textModels($settings, $prices), ...$this->listedModels($settings, $now)];
        } catch (BlogHealthCheckException $e) {
            if ($e->rejectsTheKey()) {
                $key = OpenRouterKeyCheck::Rejected;
            }

            return [...($problems ?? []), $e->getMessage()];
        }
    }

    /**
     * @return list<string>
     *
     * @throws BlogHealthCheckException
     */
    private function key(CarbonImmutable $now, ?OpenRouterKeyCheck &$check, ?CarbonImmutable &$expiresAt): array
    {
        $key = $this->openRouter->keyStatus();
        $check = OpenRouterKeyCheck::Accepted;
        $expiresAt = $key->expiresAt;
        $problems = [];

        // Une cle expiree est refusee par OpenRouter (401) : elle est signalee avant, chaque matin des 14 derniers jours.
        if ($expiresAt !== null && $expiresAt->lessThanOrEqualTo($now->addDays($this->thresholds['key_expiry_warning_days']))) {
            $problems[] = "The OpenRouter key saved in the generation settings expires on {$expiresAt->utc()->format('Y-m-d H:i')} UTC.";
        }

        if ($key->limit !== null && $key->remaining !== null && $key->remaining < $key->limit * $this->thresholds['key_remaining_percent'] / 100) {
            $problems[] = sprintf('The OpenRouter key has %.2f $ left of its %.2f $ limit.', $key->remaining, $key->limit);
        }

        return $problems;
    }

    /**
     * Chaque modele de texte du catalogue et chaque modele choisi, une fois meme s'il sert a plusieurs etapes :
     * son prix du jour, et pour un modele choisi, ce qui l'empecherait de servir. Un modele du catalogue qui
     * n'est plus propose est signale par listedModels().
     *
     * @param  array<string, array{input: float, output: float}>  $prices
     * @return list<string>
     *
     * @throws BlogHealthCheckException
     */
    private function textModels(BlogAiSettings $settings, array &$prices): array
    {
        $roles = [];

        foreach ($settings->models as $role => $modelId) {
            if ($role !== BlogAiModelCatalog::IMAGE_SETTING) {
                $roles[$modelId][] = $role;
            }
        }

        foreach (array_keys($this->textCatalog) as $modelId) {
            $roles[$modelId] ??= [];
        }

        $problems = [];

        foreach ($roles as $modelId => $modelRoles) {
            $modelId = (string) $modelId;
            $offer = $this->openRouter->offer($modelId);

            if ($offer !== null && $offer->inputCentsPerMillion !== null && $offer->outputCentsPerMillion !== null) {
                $price = ['input' => $offer->inputCentsPerMillion, 'output' => $offer->outputCentsPerMillion];
                $implausible = $this->implausiblePrice($modelId, $price);

                if ($implausible === null) {
                    $prices[$modelId] = $price;
                } else {
                    $problems[] = $implausible;
                }
            }

            if ($modelRoles === []) {
                continue;
            }

            $usedFor = implode(', ', $modelRoles);

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
        }

        return $problems;
    }

    /**
     * Un prix du jour trop loin de celui du tableau pour etre cru (OpenRouter a change d'unite, ou de modele sous
     * le meme nom) : il n'entre pas dans l'estimation, qui garde le tableau, et le probleme part au rapport.
     *
     * @param  array{input: float, output: float}  $price
     */
    private function implausiblePrice(string $modelId, array $price): ?string
    {
        $catalog = $this->textCatalog[$modelId] ?? null;

        if (! isset($catalog['input'], $catalog['output'])) {
            return null;
        }

        foreach (['input', 'output'] as $side) {
            $ratio = $catalog[$side] > 0 ? $price[$side] / $catalog[$side] : 1;

            if ($ratio > self::PLAUSIBLE_PRICE_FACTOR || $ratio < 1 / self::PLAUSIBLE_PRICE_FACTOR) {
                return sprintf(
                    'The cheapest provider of [%s] charges %s/%s cents per million tokens, too far from the catalog (config/blog.php, %s/%s) to be trusted. The estimate keeps the catalog price.',
                    $modelId, $price['input'], $price['output'], $catalog['input'], $catalog['output'],
                );
            }
        }

        return null;
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
