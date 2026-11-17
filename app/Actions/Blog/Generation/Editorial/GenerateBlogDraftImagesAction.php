<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation\Editorial;

use App\Actions\Blog\Image\StoreBlogImageAction;
use App\Contracts\Blog\Generation\GenerationStep;
use App\Contracts\Blog\Generation\StockImageProvider;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\GenerationUsage;
use App\Data\Blog\StoreBlogImageData;
use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\BlogImageUsage;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogEditorialException;
use App\Models\BlogImage;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\Editorial\Images\BlogAiImageGenerator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Etape 10 · les images d'un article genere (taches 6.8 a 6.10). L'image principale toujours, sauf si la
 * generation n'en demande aucune, puis les images du texte prevues par le redacteur, dans la limite reglee.
 * Selon la generation, une photo de banque (avec son credit) ou une image generee par IA. Chaque image passe
 * par le meme enregistrement qu'une image envoyee au back-office, avec ses descriptions dans les trois
 * langues ; elle reste en attente jusqu'a l'assemblage, et part au menage si la generation n'aboutit pas.
 *
 * Une image introuvable ne fait pas echouer la generation : le brouillon arrive sans elle, et le journal le
 * dit. Il en va de meme d'une image generee dont la reserve ne tient plus sous le plafond : elle n'est pas
 * demandee. Seul un credit epuise chez le fournisseur arrete tout.
 *
 * C'est une Action, comme l'assemblage : elle ecrit (images) et appelle StoreBlogImageAction.
 */
final readonly class GenerateBlogDraftImagesAction implements GenerationStep
{
    /** Taille minimale d'une image du texte (l'image principale suit le minimum du blog, 1200 x 630). */
    private const CONTENT_MIN_WIDTH = 800;

    private const CONTENT_MIN_HEIGHT = 450;

    public function __construct(
        private StockImageProvider $stock,
        private BlogAiImageGenerator $generator,
        private StoreBlogImageAction $storeImage,
        private BlogAiModelCatalog $models,
        private int $featuredMinWidth = 1200,
        private int $featuredMinHeight = 630,
    ) {}

    public function key(): string
    {
        return 'images';
    }

    public function timeoutSeconds(): int
    {
        return 600;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $options = GenerationImageOptions::fromPayload($context->payload) ?? GenerationImageOptions::fromSettings($context->settings);

        if ($options->mode === BlogAiImageMode::None) {
            return GenerationStepResult::continue(['mode' => 'none', 'images' => [], 'warnings' => []], summary: __('Aucune image n\'était demandée pour cet article.'));
        }

        $writing = $context->output('writing');

        if (! is_array($writing['featured_image'] ?? null)) {
            throw BlogEditorialException::missingInput($this->key(), 'the image descriptions of the writer');
        }

        $requests = [0 => $writing['featured_image']];

        foreach (array_slice($writing['inline_images'] ?? [], 0, max(0, $options->maxImages - 1)) as $image) {
            $requests[(int) $image['number']] = $image;
        }

        $model = $options->mode === BlogAiImageMode::Generated ? (string) $context->settings->modelFor(BlogAiModelCatalog::IMAGE_SETTING) : null;
        $usage = new GenerationUsage(model: $model);
        $images = [];
        $warnings = [];
        $usedStockIds = [];

        foreach ($requests as $number => $request) {
            $left = $context->budgetLeftAfter($usage);

            if ($model !== null && $left !== null && $this->models->imageReserveMicrousd($model) > $left) {
                Log::warning('Blog generation image not requested: it could exceed the spend cap.', ['generation' => $context->generationId, 'image' => $number, 'left_microusd' => $left]);
                $warnings[] = $number === 0 ? 'no main image' : "no image {$number}";

                continue;
            }

            $alt = array_filter([
                'en' => trim((string) ($request['description'] ?? '')),
                'fr' => $context->output('translation_fr')['image_alts'][(string) $number] ?? null,
                'es' => $context->output('translation_es')['image_alts'][(string) $number] ?? null,
            ], filled(...));

            try {
                $stored = $options->mode === BlogAiImageMode::Stock
                    ? $this->fromStock($request, $number, $alt, $context, $usedStockIds)
                    : $this->generated($request, $number, $alt, (string) $model);
            } catch (BlogAiProviderException $e) {
                if ($e->creditExhausted) {
                    throw $e;
                }

                $usage = $usage->plus($e->usage ?? GenerationUsage::none());
                $stored = null;
                Log::warning('Blog generation image could not be generated.', ['generation' => $context->generationId, 'image' => $number, 'exception' => $e]);
            } catch (Throwable $e) {
                $stored = null;
                Log::warning('Blog generation image could not be found or stored.', ['generation' => $context->generationId, 'image' => $number, 'exception' => $e]);
            }

            if ($stored === null) {
                $warnings[] = $number === 0 ? 'no main image' : "no image {$number}";

                continue;
            }

            if ($stored['usage'] !== null) {
                $usage = $usage->plus($stored['usage']);
            }

            $images[] = ['number' => $number, 'image_id' => $stored['image']->id, 'source' => $options->mode->value, 'credit_author' => $stored['image']->credit_author, 'credit_url' => $stored['image']->credit_url];
        }

        return GenerationStepResult::continue(
            ['mode' => $options->mode->value, 'images' => $images, 'warnings' => $warnings],
            $usage,
            $this->summary($options->mode, count($images), in_array('no main image', $warnings, true)),
        );
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, string>  $alt
     * @param  list<string>  $usedStockIds
     * @return array{image: BlogImage, usage: null}|null
     */
    private function fromStock(array $request, int $number, array $alt, GenerationStepContext $context, array &$usedStockIds): ?array
    {
        [$minWidth, $minHeight] = $number === 0 ? [$this->featuredMinWidth, $this->featuredMinHeight] : [self::CONTENT_MIN_WIDTH, self::CONTENT_MIN_HEIGHT];
        $queries = array_values(array_unique(array_filter([
            trim((string) ($request['search_query'] ?? '')),
            // A defaut, pour l'image principale, les mots-cles du sujet.
            $number === 0 ? implode(' ', array_slice($context->output('topic')['keywords'] ?? [], 0, 2)) : '',
        ])));

        foreach ($queries as $query) {
            $choice = collect($this->stock->search($query, $minWidth, $minHeight))
                ->first(fn ($image): bool => ! in_array($image->id, $usedStockIds, true));

            if ($choice === null) {
                continue;
            }

            $usedStockIds[] = $choice->id;
            $path = $this->stock->download($choice);

            try {
                $image = $this->storeImage->execute(new StoreBlogImageData(
                    path: $path,
                    usage: $number === 0 ? BlogImageUsage::Featured : BlogImageUsage::Content,
                    alt: $alt,
                    nameHint: $alt['en'] ?? null,
                    creditSource: BlogImageCreditSource::Stock,
                    creditAuthor: $choice->author ?: null,
                    creditUrl: $choice->pageUrl ?: null,
                    creditLicense: $choice->license,
                    keepSource: $number !== 0,
                ));
            } finally {
                @unlink($path);
            }

            return ['image' => $image, 'usage' => null];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, string>  $alt
     * @return array{image: BlogImage, usage: GenerationUsage}
     */
    private function generated(array $request, int $number, array $alt, string $model): array
    {
        $prompt = trim((string) ($request['generation_prompt'] ?? '')) ?: trim((string) ($request['description'] ?? ''));
        ['path' => $path, 'usage' => $usage] = $this->generator->generate($prompt, $model);

        try {
            $image = $this->storeImage->execute(new StoreBlogImageData(
                path: $path,
                usage: $number === 0 ? BlogImageUsage::Featured : BlogImageUsage::Content,
                alt: $alt,
                nameHint: $alt['en'] ?? null,
                creditSource: BlogImageCreditSource::Ai,
                creditAuthor: $this->models->name($model),
                keepSource: $number !== 0,
            ));
        } catch (Throwable $e) {
            // L'image a ete facturee : son cout reste au journal meme si elle n'est pas gardee.
            throw BlogAiProviderException::callFailed('images', $e, $usage);
        } finally {
            @unlink($path);
        }

        return ['image' => $image, 'usage' => $usage];
    }

    private function summary(BlogAiImageMode $mode, int $count, bool $missingMain): string
    {
        $done = $mode === BlogAiImageMode::Stock
            ? trans_choice('{0} Aucune photo de banque ne convenait.|{1} Une photo de banque a été choisie, avec le nom de son auteur.|[2,*] :count photos de banque ont été choisies, avec le nom de leur auteur.', $count, ['count' => $count])
            : trans_choice('{0} Aucune image n\'a pu être générée.|{1} Une image a été générée par l\'IA.|[2,*] :count images ont été générées par l\'IA.', $count, ['count' => $count]);

        return $missingMain ? $done.' '.__('Il manque l\'image principale, à ajouter avant de publier.') : $done;
    }
}
