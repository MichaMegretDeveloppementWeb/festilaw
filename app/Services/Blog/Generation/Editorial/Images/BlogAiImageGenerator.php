<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Images;

use App\Data\Blog\Generation\GenerationUsage;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Services\Blog\Generation\OpenRouterUsageReader;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Image;
use Throwable;

/**
 * Genere une image d'article par un modele d'image, a travers le meme fournisseur que les agents. Format
 * paysage, qualite moyenne (resolution « 2K » chez OpenRouter : la resolution « 1K » ferait moins des
 * 1200 px de large qu'exige l'image principale). Le fichier est ecrit dans un fichier temporaire, que
 * l'appelant supprime apres l'avoir enregistre comme image du blog. Les erreurs suivent la meme traduction
 * que pour les agents : credit epuise, ou appel rate avec ce qui a ete facture.
 */
final readonly class BlogAiImageGenerator
{
    /** Rappele a chaque demande : le modele n'a pas lu les consignes editoriales (annexe A.9). */
    private const RULES = 'Photographic style. No identifiable person, no logo or brand, no text, letters or numbers in the image.';

    public function __construct(
        private OpenRouterUsageReader $usageReader,
        private string $provider,
    ) {}

    /**
     * @return array{path: string, usage: GenerationUsage}
     *
     * @throws BlogAiProviderException
     */
    public function generate(string $prompt, string $model, int $timeoutSeconds = 120): array
    {
        try {
            $response = Image::of(trim($prompt).' '.self::RULES)
                ->landscape()
                ->quality('medium')
                ->timeout($timeoutSeconds)
                ->generate($this->provider, $model);
        } catch (InsufficientCreditsException $e) {
            throw BlogAiProviderException::creditExhausted($e, new GenerationUsage(model: $model));
        } catch (Throwable $e) {
            throw BlogAiProviderException::callFailed('images', $e, new GenerationUsage(model: $model));
        }

        $raw = $response->raw?->json();
        $usage = $this->usageReader->read(
            is_array($raw) && is_array($raw['usage'] ?? null)
                ? $raw
                : ['usage' => ['prompt_tokens' => $response->usage->inputTokens, 'completion_tokens' => $response->usage->outputTokens]],
            $model,
        );

        $content = $response->images->isEmpty() ? '' : $response->firstImage()->content();

        if ($content === '') {
            throw BlogAiProviderException::callFailed('images', new \RuntimeException('The image model returned no image.'), $usage);
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'blog-ai-image-');
        file_put_contents($path, $content);

        return ['path' => $path, 'usage' => $usage];
    }
}
