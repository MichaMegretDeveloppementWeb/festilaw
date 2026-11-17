<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationLength;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationBudget;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Illuminate\Support\Str;

/**
 * Met en file une generation demandee par une personne. Elle n'execute rien : le worker la prend dans les
 * secondes qui suivent. Refusee quand le plafond de depense du mois est atteint, quand le cout estime d'un
 * article peut depasser le plafond par article (le bouton de la fenetre ne suffit pas : la commande en ligne
 * passe aussi par ici), ou quand la configuration du pipeline ne permet pas de generer (mieux vaut le dire au
 * clic qu'a la premiere etape).
 *
 * Le plafond de brouillons en attente ne s'applique pas ici : il ne suspend que la generation automatique.
 */
final readonly class QueueBlogGenerationAction
{
    public function __construct(
        private GenerationPipeline $pipeline,
        private BlogAiSettingsService $settings,
        private BlogGenerationBudget $budget,
        private ArticleLengthRules $length,
        private BlogGenerationCostForecaster $forecaster,
        private BlogAiCostFormatter $costs,
    ) {}

    /** @return int l'identifiant de la generation creee */
    public function execute(QueueBlogGenerationData $data): int
    {
        $this->pipeline->steps($data->type);

        $settings = $this->settings->current();

        if ($this->budget->spendCapReached($settings)) {
            throw BlogGenerationException::spendCapReached($this->budget->spentThisMonthMicrousd(), $settings->monthlySpendCapMicrousd());
        }

        $topicHint = trim((string) $data->topicHint);
        $instructions = trim((string) $data->instructions);
        $images = $data->images ?? GenerationImageOptions::fromSettings($settings);
        $words = $this->length->clamp($data->articleWords ?? $settings->articleWords);

        if ($data->type === BlogGenerationType::Article) {
            $forecast = $this->forecaster->forSettings($settings, $words, $images, topicImposed: $topicHint !== '');

            if ($forecast->exceeds($settings->articleSpendCapMicrousd())) {
                throw BlogGenerationException::forecastAboveArticleCap(
                    $forecast->highMicrousd,
                    $settings->articleSpendCapMicrousd(),
                    $this->costs->dollars($forecast->highMicrousd),
                    $this->costs->dollars($settings->articleSpendCapMicrousd()),
                );
            }
        }

        return BlogGeneration::query()->create([
            'type' => $data->type,
            'trigger' => BlogGenerationTrigger::Manual,
            'status' => BlogGenerationStatus::Queued,
            'requested_by' => $data->requestedBy,
            'batch_uuid' => (string) Str::uuid(),
            'topic_hint' => $topicHint !== '' ? $topicHint : null,
            'instructions' => $instructions !== '' ? $instructions : null,
            // Les images et la longueur sont celles de CETTE generation : le pipeline ne lira pas les reglages du moment.
            'payload' => [
                ...$data->payload,
                GenerationImageOptions::PAYLOAD_KEY => $images->toPayload(),
                ...($data->type === BlogGenerationType::Article ? [GenerationLength::PAYLOAD_KEY => GenerationLength::payloadFor($words)] : []),
            ],
        ])->id;
    }
}
