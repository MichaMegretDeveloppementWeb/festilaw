<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Ecrit la ligne d'une etape : debut d'un essai, fin reussie, echec. C'est cet enregistrement, fait avant de
 * passer a l'etape suivante, qui permet de reprendre une generation coupee. La consommation se cumule sur
 * tous les essais, et le cout total de la generation est recopie sur sa ligne a chaque ecriture.
 */
final readonly class BlogGenerationStepRecorder
{
    /** Longueur maximale du message d'erreur conserve sur la ligne (le detail complet est au journal). */
    private const ERROR_MAX_LENGTH = 1_000;

    /** Ouvre un essai : la ligne passe « en cours » et son compteur d'essais avance d'un. */
    public function begin(int $generationId, string $step, int $position): BlogGenerationStep
    {
        $row = BlogGenerationStep::query()->firstOrCreate(
            ['blog_generation_id' => $generationId, 'step' => $step],
            ['position' => $position],
        );

        $row->update([
            'position' => $position,
            'status' => BlogGenerationStepStatus::Running,
            'attempt' => $row->attempt + 1,
            'error' => null,
            'started_at' => CarbonImmutable::now(),
            'finished_at' => null,
        ]);

        return $row;
    }

    public function complete(BlogGenerationStep $row, GenerationStepResult $result, int $durationMs): void
    {
        $row->update([
            'status' => BlogGenerationStepStatus::Completed,
            'output' => $result->output,
            'finished_at' => CarbonImmutable::now(),
            ...$this->consumption($row, $result->usage, $durationMs),
        ]);

        $this->refreshGenerationCost($row->blog_generation_id);
    }

    public function fail(BlogGenerationStep $row, string $error, ?GenerationUsage $usage, int $durationMs): void
    {
        $row->update([
            'status' => BlogGenerationStepStatus::Failed,
            'error' => Str::limit($error, self::ERROR_MAX_LENGTH),
            'finished_at' => CarbonImmutable::now(),
            ...$this->consumption($row, $usage ?? GenerationUsage::none(), $durationMs),
        ]);

        $this->refreshGenerationCost($row->blog_generation_id);
    }

    /**
     * La consommation de la ligne apres ajout de celle de l'essai qui vient de finir.
     *
     * @return array<string, mixed>
     */
    private function consumption(BlogGenerationStep $row, GenerationUsage $usage, int $durationMs): array
    {
        return [
            'model' => $usage->model ?? $row->model,
            'input_tokens' => $row->input_tokens + $usage->inputTokens,
            'output_tokens' => $row->output_tokens + $usage->outputTokens,
            'cached_tokens' => $row->cached_tokens + $usage->cachedTokens,
            'reasoning_tokens' => $row->reasoning_tokens + $usage->reasoningTokens,
            'web_searches' => $row->web_searches + $usage->webSearches,
            'cost_microusd' => $row->cost_microusd + $usage->costMicrousd,
            'cost_is_estimated' => $row->cost_is_estimated || $usage->costIsEstimated,
            'duration_ms' => $row->duration_ms + $durationMs,
        ];
    }

    /** Recopie sur la generation la somme des couts de ses etapes. Rejouable sans effet. */
    private function refreshGenerationCost(int $generationId): void
    {
        $total = (int) BlogGenerationStep::query()->where('blog_generation_id', $generationId)->sum('cost_microusd');

        BlogGeneration::query()->whereKey($generationId)->update(['cost_microusd' => $total]);
    }
}
