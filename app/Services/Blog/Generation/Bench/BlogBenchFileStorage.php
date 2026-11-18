<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Bench;

use App\Exceptions\Blog\BlogBenchException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Les fichiers de resultats du banc d'essai, sur le disque prive : `{dossier}/{banc}/topics/{sujet}.json`,
 * `articles/{sujet}--{configuration}.json` et `images/{sujet}.{extension}`. Chaque fichier s'ecrit sous un nom
 * provisoire puis prend son nom : un banc coupe en route ne laisse jamais un resultat a moitie ecrit, et sa
 * reprise refait ce qui manque sans repayer ce qui existe.
 */
final readonly class BlogBenchFileStorage
{
    private const IMAGE_EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(private string $directory) {}

    /** @return array<string, mixed>|null */
    public function topic(string $run, string $topicKey): ?array
    {
        return $this->read($this->path($run, "topics/{$topicKey}.json"));
    }

    /** @param  array<string, mixed>  $record */
    public function saveTopic(string $run, string $topicKey, array $record): void
    {
        $this->write($this->path($run, "topics/{$topicKey}.json"), $this->json($record));
    }

    /** @return array<string, mixed>|null */
    public function article(string $run, string $topicKey, string $configurationKey): ?array
    {
        return $this->read($this->path($run, "articles/{$topicKey}--{$configurationKey}.json"));
    }

    /** @param  array<string, mixed>  $record */
    public function saveArticle(string $run, string $topicKey, string $configurationKey, array $record): void
    {
        $this->write($this->path($run, "articles/{$topicKey}--{$configurationKey}.json"), $this->json($record));
    }

    /** L'image deja generee pour ce sujet (chemin relatif au disque), ou nul. */
    public function image(string $run, string $topicKey): ?string
    {
        foreach (self::IMAGE_EXTENSIONS as $extension) {
            $path = $this->path($run, "images/{$topicKey}.{$extension}");

            if ($this->disk()->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Range l'image ecrite par le generateur dans un fichier temporaire, avec l'extension de son type reel, puis
     * supprime ce fichier temporaire.
     */
    public function saveImage(string $run, string $topicKey, string $temporaryPath): string
    {
        try {
            $mime = (string) mime_content_type($temporaryPath);
            $extension = self::IMAGE_EXTENSIONS[$mime] ?? throw BlogBenchException::unexpectedImage($mime);
            $path = $this->path($run, "images/{$topicKey}.{$extension}");
            $this->write($path, (string) file_get_contents($temporaryPath));

            return $path;
        } finally {
            $this->deleteTemporary($temporaryPath);
        }
    }

    /** Le chemin complet d'un banc, pour le dire a la personne qui l'a lance. */
    public function location(string $run): string
    {
        return $this->disk()->path($this->path($run, ''));
    }

    private function path(string $run, string $file): string
    {
        return rtrim("{$this->directory}/{$run}/{$file}", '/');
    }

    /** @return array<string, mixed>|null */
    private function read(string $path): ?array
    {
        if (! $this->disk()->exists($path)) {
            return null;
        }

        $data = json_decode((string) $this->disk()->get($path), true);

        return is_array($data) ? $data : null;
    }

    private function write(string $path, string $contents): void
    {
        $this->disk()->put("{$path}.part", $contents);
        $this->disk()->move("{$path}.part", $path);
    }

    /** @param  array<string, mixed>  $record */
    private function json(array $record): string
    {
        return (string) json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function deleteTemporary(string $path): void
    {
        try {
            if (is_file($path)) {
                unlink($path);
            }
        } catch (Throwable $e) {
            Log::warning('File left behind: delete failed.', ['path' => $path, 'exception' => $e]);
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
