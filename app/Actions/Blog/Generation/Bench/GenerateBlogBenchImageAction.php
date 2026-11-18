<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation\Bench;

use App\Data\Blog\Generation\Bench\BlogBenchTopic;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogBenchException;
use App\Services\Blog\Generation\Bench\BlogBenchFileStorage;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\Editorial\Images\BlogAiImageGenerator;
use Illuminate\Support\Facades\Log;

/**
 * Genere l'image principale d'un sujet du banc, une seule fois : le premier article ecrit en donne la
 * description, les suivants la partagent (decision de Micha du 18/11, l'image ne depend pas du modele de texte).
 * Comme dans le pipeline, l'appel ne part que si sa reserve tient sous le plafond. Une image ratee ne fait pas
 * echouer l'article : elle est retentee par l'article suivant du meme sujet.
 */
final readonly class GenerateBlogBenchImageAction
{
    public function __construct(
        private BlogAiImageGenerator $generator,
        private BlogBenchFileStorage $storage,
        private BlogAiModelCatalog $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $writing  la sortie de la redaction, qui decrit l'image
     * @return array<string, mixed> ce que garde le fichier de l'article
     *
     * @throws BlogBenchException
     */
    public function execute(string $run, BlogBenchTopic $topic, array $writing, string $model, int $budgetLeftMicrousd): array
    {
        $existing = $this->storage->image($run, $topic->key);

        if ($existing !== null) {
            return ['generated' => false, 'reason' => 'shared', 'path' => $existing, 'cost_microusd' => 0];
        }

        $request = is_array($writing['featured_image'] ?? null) ? $writing['featured_image'] : [];
        $prompt = trim((string) ($request['generation_prompt'] ?? '')) ?: trim((string) ($request['description'] ?? ''));

        if ($prompt === '') {
            return ['generated' => false, 'reason' => 'no description', 'cost_microusd' => 0];
        }

        if ($this->catalog->imageReserveMicrousd($model) > $budgetLeftMicrousd) {
            return ['generated' => false, 'reason' => 'over budget', 'prompt' => $prompt, 'cost_microusd' => 0];
        }

        $startedAt = hrtime(true);

        try {
            ['path' => $temporary, 'usage' => $usage] = $this->generator->generate($prompt, $model);
        } catch (BlogAiProviderException $e) {
            if ($e->creditExhausted) {
                Log::error('Blog bench stopped: the provider credit is exhausted.', ['topic' => $topic->key, 'exception' => $e]);

                throw BlogBenchException::creditExhausted($e);
            }

            Log::warning('Blog bench image failed, the next article of the topic will try again.', ['topic' => $topic->key, 'exception' => $e]);

            return ['generated' => false, 'reason' => 'failed', 'prompt' => $prompt, 'cost_microusd' => $e->usage?->costMicrousd ?? 0];
        }

        return [
            'generated' => true,
            'path' => $this->storage->saveImage($run, $topic->key, $temporary),
            'prompt' => $prompt,
            'model' => $usage->model ?? $model,
            'cost_microusd' => $usage->costMicrousd,
            'cost_is_estimated' => $usage->costIsEstimated,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }
}
