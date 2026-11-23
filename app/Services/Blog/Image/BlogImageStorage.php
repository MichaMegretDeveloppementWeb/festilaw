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
 * Seul endroit qui ecrit, lit et supprime les fichiers des images du blog. Les tailles servies au public vont
 * sur le disque public ; la source non decoupee d'une image recadrable va sur le disque prive, sous le meme
 * chemin (audit du 23/11 : publique, elle montrait ce que le recadrage avait retire). Les disques sont
 * configures pour lever une exception en cas d'echec ; ces exceptions sont converties ici en exception typee,
 * ou journalisees quand le nettoyage est fait "au mieux".
 */
final readonly class BlogImageStorage
{
    public function __construct(
        private FilesystemFactory $filesystems,
        private BlogImagePaths $paths,
        private string $disk,
        private string $sourceDisk,
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
                $this->sourceDisk()->put($this->paths->source($uuid, $basename), $image->source);
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
            $contents = ($image->hasSource() ? $this->sourceDisk() : $this->disk())->get($path);
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
            $this->sourceDisk()->deleteDirectory($this->paths->directory($uuid));

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
     * Identifiants (uuid) des dossiers d'image presents sur l'un ou l'autre disque.
     *
     * @return list<string>
     */
    public function directories(): array
    {
        $uuids = [];

        foreach ([$this->disk(), $this->sourceDisk()] as $disk) {
            if ($disk->exists($this->paths->root())) {
                array_push($uuids, ...array_map(basename(...), $disk->directories($this->paths->root())));
            }
        }

        return array_values(array_unique($uuids));
    }

    /** Date du fichier le plus recent d'un dossier, sur les deux disques (horodatage Unix), ou null s'il est vide. */
    public function lastModified(string $uuid): ?int
    {
        $times = [];

        foreach ([$this->disk(), $this->sourceDisk()] as $disk) {
            foreach ($disk->files($this->paths->directory($uuid)) as $file) {
                $times[] = $disk->lastModified($file);
            }
        }

        return $times === [] ? null : max($times);
    }

    /** Les fichiers d'une image sont-ils sur le disque ? Sa plus grande taille suffit a le dire. */
    public function variantsExist(BlogImage $image): bool
    {
        $widths = $image->widths();

        return $widths === [] || $this->disk()->exists($this->paths->variantOf($image, max($widths)));
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk($this->disk);
    }

    private function sourceDisk(): Filesystem
    {
        return $this->filesystems->disk($this->sourceDisk);
    }
}
