<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\GenerationEffort;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationLength;
use App\Data\Blog\Generation\GenerationModels;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Models\BlogGeneration;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Illuminate\Support\Str;

/**
 * Met en file une generation demandee par une personne. Elle n'execute rien : le worker la prend dans les
 * secondes qui suivent. Refusee seulement quand la configuration du pipeline ne permet pas de generer (mieux
 * vaut le dire au clic qu'a la premiere etape). Le cout ne refuse rien (decision de Micha du 18/11) : son
 * estimation est annoncee avant le clic et gardee sur la generation, qui s'arrete par securite au double du
 * haut annonce. Le plafond du mois ne fait qu'avertir (decision du 17/11).
 *
 * Le plafond de brouillons en attente ne s'applique pas ici : il ne suspend que la generation automatique.
 */
final readonly class QueueBlogGenerationAction
{
    public function __construct(
        private GenerationPipeline $pipeline,
        private BlogAiSettingsService $settings,
        private ArticleLengthRules $length,
        private BlogGenerationCostForecaster $forecaster,
    ) {}

    /** @return int l'identifiant de la generation creee */
    public function execute(QueueBlogGenerationData $data): int
    {
        $this->pipeline->steps($data->type);

        $settings = $this->settings->current();
        $topicHint = trim((string) $data->topicHint);
        $instructions = trim((string) $data->instructions);
        $images = $data->images ?? GenerationImageOptions::fromSettings($settings);
        $words = $this->length->clamp($data->articleWords ?? $settings->articleWords);
        // Les images, la longueur, l'effort et les modeles sont ceux de CETTE generation : le pipeline ne lira pas
        // les reglages du moment (modeles figes depuis le controle du 20/11).
        $payload = [
            ...$data->payload,
            GenerationImageOptions::PAYLOAD_KEY => $images->toPayload(),
            GenerationEffort::PAYLOAD_KEY => ($data->effort ?? $settings->effort)->value,
            GenerationModels::PAYLOAD_KEY => GenerationModels::payloadFor($settings),
            ...($data->type === BlogGenerationType::Article ? [GenerationLength::PAYLOAD_KEY => GenerationLength::payloadFor($words)] : []),
        ];
        $forecast = $this->forecaster->forGeneration($settings, $data->type, $payload, topicImposed: $topicHint !== '');

        return BlogGeneration::query()->create([
            'type' => $data->type,
            'trigger' => BlogGenerationTrigger::Manual,
            'status' => BlogGenerationStatus::Queued,
            'requested_by' => $data->requestedBy,
            'source_blog_post_id' => $data->sourcePostId,
            'batch_uuid' => (string) Str::uuid(),
            'topic_hint' => $topicHint !== '' ? $topicHint : null,
            'instructions' => $instructions !== '' ? $instructions : null,
            'payload' => $payload,
            'forecast_low_microusd' => $forecast->lowMicrousd,
            'forecast_high_microusd' => $forecast->highMicrousd,
        ])->id;
    }
}
