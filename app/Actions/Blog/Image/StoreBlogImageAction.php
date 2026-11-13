<?php

declare(strict_types=1);

namespace App\Actions\Blog\Image;

use App\Data\Blog\StoreBlogImageData;
use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Services\Blog\Image\BlogImageInspector;
use App\Services\Blog\Image\BlogImageProcessor;
use App\Services\Blog\Image\BlogImageStorage;
use App\Services\Blog\Image\BlogImageTextCleaner;
use App\Services\Blog\Slug\BlogSlugGenerator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Enregistre une image du blog : controle du fichier, production des variantes, ecriture des
 * fichiers, puis de la ligne. Les fichiers sont ecrits AVANT la ligne (le pire cas est un dossier
 * sans ligne, invisible et repris par la purge, jamais une ligne qui annonce un fichier absent) et
 * sont retires si la ligne ne peut pas etre creee. L'image nait "en attente" (sans article) : c'est
 * l'enregistrement de l'article qui la rattache.
 */
final readonly class StoreBlogImageAction
{
    private const FALLBACK_BASENAME = 'image';

    public function __construct(
        private BlogImageInspector $inspector,
        private BlogImageProcessor $processor,
        private BlogImageStorage $storage,
        private BlogImageTextCleaner $cleaner,
        private BlogSlugGenerator $slugs,
    ) {}

    /**
     * @throws BlogImageException
     */
    public function execute(StoreBlogImageData $data): BlogImage
    {
        $this->inspector->inspect($data->path, $data->usage);

        $processed = $this->processor->process($data->path, $data->usage);

        $alt = $this->cleaner->alt($data->alt);
        $uuid = (string) Str::uuid();
        $basename = $this->basename($data->nameHint, $alt);

        $this->storage->write($uuid, $basename, $processed);

        try {
            return BlogImage::query()->create([
                'uuid' => $uuid,
                'basename' => $basename,
                'variants' => ['widths' => $processed->widths(), 'share' => $processed->share !== null],
                'width' => $processed->width,
                'height' => $processed->height,
                'alt' => $alt,
                'credit_source' => $data->creditSource,
                'credit_author' => $this->cleaner->credit($data->creditAuthor),
                'credit_url' => $this->cleaner->creditUrl($data->creditUrl),
                'credit_license' => $this->cleaner->credit($data->creditLicense),
            ]);
        } catch (Throwable $e) {
            $this->storage->deleteQuietly($uuid);

            throw $e;
        }
    }

    /**
     * Nom lisible des fichiers, genere par le serveur a partir du texte fourni (jamais du nom du
     * fichier envoye) et fige a l'envoi.
     *
     * @param  array<string, string>|null  $alt
     */
    private function basename(?string $nameHint, ?array $alt): string
    {
        foreach ([$nameHint, ...array_values($alt ?? [])] as $candidate) {
            $slug = $this->slugs->fromText((string) $candidate);

            if ($slug !== '') {
                return $slug;
            }
        }

        return self::FALLBACK_BASENAME;
    }
}
