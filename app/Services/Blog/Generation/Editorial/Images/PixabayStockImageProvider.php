<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Images;

use App\Contracts\Blog\Generation\StockImageProvider;
use App\Data\Blog\Generation\StockImage;
use App\Exceptions\Blog\BlogEditorialException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * La banque Pixabay (documentation de l'API relue le 2026-11-17). Les recherches sont gardees 24 heures,
 * comme l'API l'exige. Une photo n'est jamais affichee depuis Pixabay : elle est telechargee une fois, depuis
 * les seuls hotes de Pixabay, puis traitee et hebergee comme toute image du blog. Avec une cle gratuite, le
 * fichier fait 1280 px de cote au plus : la recherche ne garde que les photos dont ce fichier atteint la
 * taille demandee.
 */
final readonly class PixabayStockImageProvider implements StockImageProvider
{
    private const ENDPOINT = 'https://pixabay.com/api/';

    private const LARGE_SIDE = 1280;

    private const DOWNLOAD_HOSTS = ['pixabay.com', 'cdn.pixabay.com'];

    private const MAX_DOWNLOAD_BYTES = 10 * 1024 * 1024;

    private const LICENSE = 'Pixabay Content License';

    public function __construct(private ?string $apiKey) {}

    public function search(string $query, int $minWidth, int $minHeight): array
    {
        if (blank($this->apiKey)) {
            throw BlogEditorialException::imageSourceFailed('no Pixabay API key (PIXABAY_API_KEY)');
        }

        $query = Str::limit(trim($query), 100, '');
        $hits = Cache::remember('blog:pixabay:'.sha1($query), now()->addDay(), function () use ($query): array {
            try {
                $response = Http::timeout(15)->connectTimeout(5)->get(self::ENDPOINT, [
                    'key' => $this->apiKey,
                    'q' => $query,
                    'lang' => 'en',
                    'image_type' => 'photo',
                    'orientation' => 'horizontal',
                    'safesearch' => 'true',
                    'order' => 'popular',
                    'per_page' => 30,
                ]);
            } catch (Throwable $e) {
                throw BlogEditorialException::imageSourceFailed('Pixabay did not answer: '.$e->getMessage());
            }

            if (! $response->successful()) {
                throw BlogEditorialException::imageSourceFailed("Pixabay answered HTTP {$response->status()}");
            }

            return array_values(array_filter((array) $response->json('hits'), is_array(...)));
        });

        $images = [];

        foreach ($hits as $hit) {
            $width = (int) ($hit['imageWidth'] ?? 0);
            $height = (int) ($hit['imageHeight'] ?? 0);
            $url = (string) ($hit['largeImageURL'] ?? '');

            if ($width <= 0 || $height <= 0 || $url === '') {
                continue;
            }

            // Le fichier telecharge fait 1280 px sur son plus grand cote, sans agrandir l'original.
            $scale = min(1, self::LARGE_SIDE / max($width, $height));
            $largeWidth = (int) floor($width * $scale);
            $largeHeight = (int) floor($height * $scale);

            if ($largeWidth < $minWidth || $largeHeight < $minHeight) {
                continue;
            }

            $images[] = new StockImage(
                id: (string) ($hit['id'] ?? ''),
                downloadUrl: $url,
                width: $largeWidth,
                height: $largeHeight,
                pageUrl: (string) ($hit['pageURL'] ?? ''),
                author: (string) ($hit['user'] ?? ''),
                license: self::LICENSE,
            );
        }

        return $images;
    }

    public function download(StockImage $image): string
    {
        $host = strtolower((string) parse_url($image->downloadUrl, PHP_URL_HOST));

        if (parse_url($image->downloadUrl, PHP_URL_SCHEME) !== 'https' || ! in_array($host, self::DOWNLOAD_HOSTS, true)) {
            throw BlogEditorialException::imageSourceFailed("refused to download from [{$host}]");
        }

        try {
            $response = Http::timeout(30)->connectTimeout(5)->withoutRedirecting()->get($image->downloadUrl);
        } catch (Throwable $e) {
            throw BlogEditorialException::imageSourceFailed('download failed: '.$e->getMessage());
        }

        $body = $response->body();

        if (! $response->successful() || $body === '' || strlen($body) > self::MAX_DOWNLOAD_BYTES) {
            throw BlogEditorialException::imageSourceFailed("download answered HTTP {$response->status()} with ".strlen($body).' bytes');
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'blog-stock-');
        file_put_contents($path, $body);

        return $path;
    }
}
