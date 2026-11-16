<?php

declare(strict_types=1);

namespace App\Services\Blog\Image;

use App\Data\Blog\ProcessedBlogImage;
use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Seul endroit qui ecrit, lit et supprime les fichiers des images du blog. Le disque est configure
 * pour lever une exception en cas d'echec ; ces exceptions sont converties ici en exception typee, ou
 * journalisees quand le nettoyage est fait "au mieux".
 */
final readonly class BlogImageStorage
{
    public function __construct(
        private FilesystemFactory $filesystems,
        private BlogImagePaths $paths,
        private string $disk,
    ) {}

    /**
     * Ecrit tous les fichiers d'une image. Si l'un echoue, ceux deja ecrits sont retires.
     *
     * @throws BlogImageException
     */
    public function write(string $uuid, string $basename, ProcessedBlogImage $image): void
    {
        try {
            foreach ($image->variants as $width => $contents) {
                $this->disk()->put($this->paths->variant($uuid, $basename, $width), $contents);
            }

            if ($image->share !== null) {
                $this->disk()->put($this->paths->share($uuid, $basename), $image->share);
            }

            if ($image->source !== null) {
                $this->disk()->put($this->paths->source($uuid, $basename), $image->source);
            }
        } catch (Throwable $e) {
            $this->deleteQuietly($uuid);

            throw BlogImageException::storageFailed($uuid, $e);
        }
    }

    /**
     * Contenu de la source gardee d'une image, pour la recadrer de nouveau.
     *
     * @throws BlogImageException
     */
    public function readSource(BlogImage $image): string
    {
        $path = $this->paths->recropSourceOf($image);

        if ($path === null) {
            throw BlogImageException::notRecroppable($image->id);
        }

        try {
            $contents = $this->disk()->get($path);
        } catch (Throwable $e) {
            throw BlogImageException::storageFailed($image->uuid, $e);
        }

        if (! is_string($contents) || $contents === '') {
            throw BlogImageException::notRecroppable($image->id);
        }

        return $contents;
    }

    /**
     * Nettoyage au mieux du dossier d'une image : un echec ne masque jamais l'erreur d'origine ni ne
     * casse le parcours, mais il est journalise pour que les fichiers restes en place soient retrouves
     * (la purge planifiee repasse dessus).
     */
    public function deleteQuietly(string $uuid): bool
    {
        try {
            $this->disk()->deleteDirectory($this->paths->directory($uuid));

            return true;
        } catch (Throwable $e) {
            Log::warning('Blog image files left behind: delete failed.', [
                'directory' => $this->paths->directory($uuid),
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Identifiants (uuid) des dossiers d'image presents sur le disque.
     *
     * @return list<string>
     */
    public function directories(): array
    {
        return array_map(basename(...), $this->disk()->directories($this->paths->root()));
    }

    /** Date du fichier le plus recent d'un dossier (horodatage Unix), ou null s'il est vide. */
    public function lastModified(string $uuid): ?int
    {
        $times = array_map(
            fn (string $file): int => $this->disk()->lastModified($file),
            $this->disk()->files($this->paths->directory($uuid)),
        );

        return $times === [] ? null : max($times);
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk($this->disk);
    }
}
