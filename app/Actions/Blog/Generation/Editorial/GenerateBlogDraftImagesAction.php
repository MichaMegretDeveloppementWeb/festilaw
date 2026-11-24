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
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\BaseAppException;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogEditorialException;
use App\Models\BlogImage;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\Editorial\Images\BlogAiImageGenerator;
use App\Services\Blog\Generation\Editorial\Images\StockPhotoChecker;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avant-derniere etape : les images d'un article genere (taches 6.8 a 6.10). L'image principale toujours, sauf si la
 * generation n'en demande aucune, puis les images du texte prevues par le redacteur, dans la limite reglee.
 * Selon la generation, une photo de banque (avec son credit) ou une image generee par IA. Une photo de banque
 * n'est prise qu'apres un controle qui la regarde et la decrit telle qu'elle est (StockPhotoChecker, remarque de
 * Micha du 20/11) ; aucune ne convient, et l'article arrive sans elle. Chaque image passe
 * par le meme enregistrement qu'une image envoyee au back-office, avec ses descriptions dans les trois
 * langues ; elle reste en attente jusqu'a l'assemblage, et part au menage si la generation n'aboutit pas.
 *
 * Une image introuvable ne fait pas echouer la generation : le brouillon arrive sans elle, et le journal le
 * dit. Il en va de meme d'une image generee dont la reserve ne tient plus sous l'arret de securite : elle n'est pas
 * demandee. Seul un credit epuise chez le fournisseur arrete tout.
 *
 * C'est une Action, comme l'assemblage : elle ecrit (images) et appelle StoreBlogImageAction.
 */
final readonly class GenerateBlogDraftImagesAction implements GenerationStep
{
    /** Taille minimale d'une image du texte (l'image principale suit le minimum du blog, 1200 x 630). */
    private const CONTENT_MIN_WIDTH = 800;

    private const CONTENT_MIN_HEIGHT = 450;

    /** Pourquoi une image manque (controle du 20/11), dans la sortie de l'etape et dans son resume. */
    private const CAUSE_NONE_FIT = 'none_fit';

    private const CAUSE_CHECK = 'check_failed';

    private const CAUSE_BANK = 'bank_unavailable';

    private const CAUSE_FAILED = 'failed';

    /**
     * Une image trouvee ou generee que le serveur n'a pas pu enregistrer (disque plein, droits, traitement de
     * l'image) : une panne du serveur, pas un choix de l'IA, nommee comme telle et portee au rapport du matin
     * (audit du 23/11).
     */
    private const CAUSE_STORAGE = 'storage_failed';

    public function __construct(
        private StockImageProvider $stock,
        private BlogAiImageGenerator $generator,
        private StoreBlogImageAction $storeImage,
        private BlogAiModelCatalog $models,
        private StockPhotoChecker $checker,
        private int $featuredMinWidth = 1200,
        private int $featuredMinHeight = 630,
        private int $photoChecks = 4,
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

        // Une demande d'image principale (type `image`, demande de Micha du 24/11/2026) lit tout dans la description de
        // l'etape precedente ; un article genere, dans la redaction, le choix du sujet et les versions.
        $imageRequest = $context->type === BlogGenerationType::Image;
        $brief = $imageRequest ? $context->output('image_brief') : $context->output('writing');

        if (! is_array($brief['featured_image'] ?? null)) {
            throw BlogEditorialException::missingInput($this->key(), $imageRequest ? 'the image brief' : 'the image descriptions of the writer');
        }

        $requests = [0 => $brief['featured_image']];

        foreach ($imageRequest ? [] : array_slice($brief['inline_images'] ?? [], 0, max(0, $options->maxImages - 1)) as $image) {
            $requests[(int) $image['number']] = $image;
        }

        $subject = [
            'article' => $imageRequest
                ? (array) ($brief['article'] ?? [])
                : array_intersect_key($context->output('topic'), array_flip(['title', 'angle', 'reader'])),
            // A defaut de photo pour la recherche de l'image principale, les mots-cles du sujet de l'article.
            'fallback_query' => $imageRequest ? '' : implode(' ', array_slice($context->output('topic')['keywords'] ?? [], 0, 2)),
            // Ni la photo de l'image actuelle ni celles deja obtenues pour l'article : une nouvelle demande en veut une autre.
            'skip_pages' => array_values(array_unique(array_filter([
                (string) ($context->payload['image_request']['current_credit_url'] ?? ''),
                ...array_map(strval(...), (array) ($context->payload['image_request']['obtained_credit_urls'] ?? [])),
            ]))),
        ];

        $model = $options->mode === BlogAiImageMode::Generated ? (string) $context->settings->modelFor(BlogAiModelCatalog::IMAGE_SETTING) : null;
        $usage = new GenerationUsage(model: $model);
        $images = [];
        $warnings = [];
        $causes = [];
        $usedStockIds = [];

        foreach ($requests as $number => $request) {
            // Une image peut prendre plusieurs minutes (recherches, telechargements, controles) : le verrou est
            // prolonge entre deux images, pour qu'aucun autre worker ne reprenne l'etape et ne la paie deux fois.
            $context->keepLock();
            $left = $context->budgetLeftAfter($usage);

            if ($model !== null && $left !== null && $this->models->imageReserveMicrousd($model) > $left) {
                Log::warning('Blog generation image not requested: it could pass the safety stop.', ['generation' => $context->generationId, 'image' => $number, 'left_microusd' => $left]);
                $warnings[] = $number === 0 ? 'no main image' : "no image {$number}";

                continue;
            }

            $alt = $imageRequest ? array_filter((array) ($brief['alt'] ?? []), filled(...)) : array_filter([
                'en' => trim((string) ($request['description'] ?? '')),
                'fr' => $context->output('translation_fr')['image_alts'][(string) $number] ?? null,
                'es' => $context->output('translation_es')['image_alts'][(string) $number] ?? null,
            ], filled(...));

            try {
                $stored = $options->mode === BlogAiImageMode::Stock
                    ? $this->fromStock($request, $number, $context, $usage, $usedStockIds, $subject)
                    : $this->generated($request, $number, $alt, (string) $model, $context->generationId);
            } catch (BlogAiProviderException $e) {
                if ($e->creditExhausted) {
                    throw $e;
                }

                $usage = $usage->plus($e->usage ?? GenerationUsage::none());
                $stored = null;
                Log::warning('Blog generation image could not be generated.', ['generation' => $context->generationId, 'image' => $number, 'exception' => $e]);
            } catch (Throwable $e) {
                // Un fichier refuse par les regles des images (trop petit, format) n'est qu'une photo qui ne
                // convenait pas ; tout le reste est une panne du serveur.
                $refused = $e instanceof BaseAppException && $e->isRefusal();
                $stored = $refused ? null : ['image' => null, 'usage' => null, 'cause' => self::CAUSE_STORAGE];
                Log::log($refused ? 'warning' : 'error', $refused ? 'Blog generation image was refused by the image rules.' : 'Blog generation image could not be stored.', ['generation' => $context->generationId, 'image' => $number, 'exception' => $e]);
            }

            if ($stored !== null && $stored['usage'] !== null) {
                $usage = $usage->plus($stored['usage']);
            }

            if ($stored === null || $stored['image'] === null) {
                $warnings[] = $number === 0 ? 'no main image' : "no image {$number}";
                $causes[$number] = $stored['cause'] ?? self::CAUSE_FAILED;

                continue;
            }

            $images[] = ['number' => $number, 'image_id' => $stored['image']->id, 'source' => $options->mode->value, 'credit_author' => $stored['image']->credit_author, 'credit_url' => $stored['image']->credit_url];
        }

        return GenerationStepResult::continue(
            ['mode' => $options->mode->value, 'images' => $images, 'warnings' => $warnings, 'causes' => $causes],
            $usage,
            $this->summary($options->mode, count($images), in_array('no main image', $warnings, true), $causes[0] ?? null),
        );
    }

    /**
     * La premiere photo de la banque que le controle juge convenable, rangee avec ce qu'elle montre pour texte
     * alternatif. Au plus `photoChecks` photos regardees par requete ; une photo regardee ne sert plus pour une
     * autre image de l'article. Sans photo convenable, l'image est nulle ; le cout des controles est rendu
     * dans tous les cas. Une photo qui n'a pas pu etre controlee n'est pas prise : une photo fausse vaut moins
     * qu'aucune photo.
     *
     * Sans image, la cause est rendue (controle du 20/11) : la banque n'a pas repondu, le controle n'a pas pu se
     * faire, ou aucune photo ne convenait. Le resume de l'etape le dit, et une banque en panne est journalisee en
     * erreur, donc reprise par le rapport d'erreurs du matin. Le cout des controles deja faits est rendu meme
     * quand une recherche ou un telechargement echoue ensuite.
     *
     * @param  array<string, mixed>  $request
     * @param  GenerationUsage  $spent  ce que l'etape a deja depense, pour l'arret de securite
     * @param  list<string>  $usedStockIds
     * @param  array{article: array<string, mixed>, fallback_query: string, skip_pages: list<string>}  $subject  le sujet que
     *                                                                                                           le controle lit, la recherche de secours de l'image principale et les photos a ne pas reprendre
     * @return array{image: BlogImage|null, usage: GenerationUsage, cause?: string}
     *
     * @throws BlogAiProviderException quand le credit est epuise, avec le cout des controles deja faits
     */
    private function fromStock(array $request, int $number, GenerationStepContext $context, GenerationUsage $spent, array &$usedStockIds, array $subject): array
    {
        [$minWidth, $minHeight] = $number === 0 ? [$this->featuredMinWidth, $this->featuredMinHeight] : [self::CONTENT_MIN_WIDTH, self::CONTENT_MIN_HEIGHT];
        $queries = array_values(array_unique(array_filter([
            trim((string) ($request['search_query'] ?? '')),
            $number === 0 ? $subject['fallback_query'] : '',
        ])));
        $checks = GenerationUsage::none();
        $cause = self::CAUSE_NONE_FIT;
        $downloaded = 0;

        foreach ($queries as $query) {
            try {
                $found = $this->stock->search($query, $minWidth, $minHeight);
            } catch (BlogEditorialException $e) {
                Log::error('Blog generation stock photos could not be searched.', ['generation' => $context->generationId, 'image' => $number, 'exception' => $e]);

                return ['image' => null, 'usage' => $checks, 'cause' => self::CAUSE_BANK];
            }

            $candidates = collect($found)
                ->reject(fn ($image): bool => in_array($image->id, $usedStockIds, true) || in_array($image->pageUrl, $subject['skip_pages'], true))
                ->take($this->photoChecks);

            foreach ($candidates as $candidate) {
                $usedStockIds[] = $candidate->id;

                try {
                    $path = $this->stock->download($candidate);
                } catch (BlogEditorialException $e) {
                    Log::warning('Blog generation stock photo could not be downloaded.', ['generation' => $context->generationId, 'image' => $number, 'photo' => $candidate->pageUrl, 'exception' => $e]);
                    $cause = self::CAUSE_BANK;

                    continue;
                }

                $downloaded++;

                try {
                    $verdict = $this->checker->check($path, $request, $subject['article'], $context, $spent->plus($checks));
                    $checks = $checks->plus($verdict->usage);

                    if (! $verdict->fits) {
                        Log::info('Blog generation stock photo set aside by the photo check.', ['generation' => $context->generationId, 'image' => $number, 'photo' => $candidate->pageUrl, 'reason' => $verdict->reason]);

                        continue;
                    }

                    $image = $this->storeImage->execute(new StoreBlogImageData(
                        path: $path,
                        usage: $number === 0 ? BlogImageUsage::Featured : BlogImageUsage::Content,
                        alt: $verdict->alt,
                        nameHint: $verdict->alt['en'] ?? null,
                        creditSource: BlogImageCreditSource::Stock,
                        creditAuthor: $candidate->author ?: null,
                        creditUrl: $candidate->pageUrl ?: null,
                        creditLicense: $candidate->license,
                        keepSource: $number !== 0,
                        generationId: $number === 0 ? $context->generationId : null,
                    ));

                    return ['image' => $image, 'usage' => $checks];
                } catch (BlogAiProviderException $e) {
                    if ($e->creditExhausted) {
                        throw $e->withEarlierUsage($checks);
                    }

                    Log::warning('Blog generation stock photo could not be checked, so it is not used.', ['generation' => $context->generationId, 'image' => $number, 'exception' => $e]);

                    return ['image' => null, 'usage' => $checks->plus($e->usage ?? GenerationUsage::none()), 'cause' => self::CAUSE_CHECK];
                } finally {
                    @unlink($path);
                }
            }
        }

        // Aucune photo n'a pu etre telechargee : une panne de la banque, que le rapport du matin doit porter (le
        // 24/11, Pixabay refusait chaque telechargement en HTTP 429, et seuls des avertissements le disaient).
        if ($cause === self::CAUSE_BANK && $downloaded === 0) {
            Log::error('Blog generation stock photos could not be downloaded.', ['generation' => $context->generationId, 'image' => $number]);
        }

        return ['image' => null, 'usage' => $checks, 'cause' => $cause];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, string>  $alt
     * @return array{image: BlogImage, usage: GenerationUsage}
     */
    private function generated(array $request, int $number, array $alt, string $model, int $generationId): array
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
                generationId: $number === 0 ? $generationId : null,
            ));
        } catch (Throwable $e) {
            // L'image a ete facturee : son cout reste au journal meme si elle n'est pas gardee.
            throw BlogAiProviderException::callFailed('images', $e, $usage);
        } finally {
            @unlink($path);
        }

        return ['image' => $image, 'usage' => $usage];
    }

    /** @param  string|null  $mainCause  pourquoi l'image principale manque, quand elle manque */
    private function summary(BlogAiImageMode $mode, int $count, bool $missingMain, ?string $mainCause): string
    {
        $done = match (true) {
            $count === 0 && $mainCause === self::CAUSE_STORAGE => __("Les images n'ont pas pu être enregistrées sur le serveur."),
            $mode !== BlogAiImageMode::Stock => trans_choice('{0} Aucune image n\'a pu être générée.|{1} Une image a été générée par l\'IA.|[2,*] :count images ont été générées par l\'IA.', $count, ['count' => $count]),
            $count === 0 && $mainCause === self::CAUSE_BANK => __('La banque de photos n\'a pas répondu, aucune photo n\'a pu être choisie.'),
            $count === 0 && $mainCause === self::CAUSE_CHECK => __('Les photos trouvées n\'ont pas pu être contrôlées, aucune n\'a été prise.'),
            default => trans_choice('{0} Aucune photo de banque ne convenait.|{1} Une photo de banque a été choisie, avec le nom de son auteur.|[2,*] :count photos de banque ont été choisies, avec le nom de leur auteur.', $count, ['count' => $count]),
        };

        return $missingMain ? $done.' '.__('Il manque l\'image principale, à ajouter avant de publier.') : $done;
    }
}
