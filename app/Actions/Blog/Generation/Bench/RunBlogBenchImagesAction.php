<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation\Bench;

use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogBenchException;
use App\Services\Blog\Generation\Bench\BlogBenchFileStorage;
use App\Services\Blog\Generation\Editorial\Images\BlogAiImageGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Banc d'images (C9, demande de Micha du 19/11) : redonne a un autre modele le prompt exact de l'image deja
 * generee pour un sujet du banc. La comparaison se fait donc a prompt egal, sans reecrire l'article. L'image et
 * sa fiche (cout, duree, taille) sont ecrites une fois par sujet et par modele : une reprise ne repaie rien.
 */
final readonly class RunBlogBenchImagesAction
{
    public function __construct(
        private BlogAiImageGenerator $generator,
        private BlogBenchFileStorage $storage,
    ) {}

    /**
     * @return array<string, mixed> la fiche de l'image : modele, prompt, chemin, cout, duree, taille
     *
     * @throws BlogBenchException
     */
    public function execute(string $run, string $topicKey, string $model): array
    {
        $saved = $this->storage->imageTrial($run, $topicKey, $model);

        if ($saved !== null) {
            return $saved;
        }

        $prompt = $this->generatedPrompt($run, $topicKey);
        $startedAt = hrtime(true);

        try {
            ['path' => $temporary, 'usage' => $usage] = $this->generator->generate($prompt, $model);
        } catch (BlogAiProviderException $e) {
            Log::error('Blog image bench call failed.', ['topic' => $topicKey, 'model' => $model, 'exception' => $e]);

            throw $e->creditExhausted ? BlogBenchException::creditExhausted($e) : BlogBenchException::stepFailed("{$topicKey} / {$model}", 'images', $e);
        }

        [$width, $height] = getimagesize($temporary) ?: [0, 0];

        return $this->storage->saveImageTrial($run, $topicKey, $model, $temporary, [
            'topic' => $topicKey,
            'model' => $usage->model ?? $model,
            'prompt' => $prompt,
            'cost_microusd' => $usage->costMicrousd,
            'cost_is_estimated' => $usage->costIsEstimated,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'width' => (int) $width,
            'height' => (int) $height,
            'finished_at' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    /**
     * Le prompt de l'image deja generee pour ce sujet : celui de l'article du banc dont l'image est partie.
     *
     * @throws BlogBenchException
     */
    private function generatedPrompt(string $run, string $topicKey): string
    {
        foreach ($this->storage->articleKeys($run) as $articleKey) {
            if (! str_starts_with($articleKey, $topicKey.'--')) {
                continue;
            }

            $image = (array) ($this->storage->articleByKey($run, $articleKey)['image'] ?? []);

            if (($image['generated'] ?? false) === true && filled($image['prompt'] ?? null)) {
                return (string) $image['prompt'];
            }
        }

        throw BlogBenchException::noImagePrompt($topicKey);
    }
}
