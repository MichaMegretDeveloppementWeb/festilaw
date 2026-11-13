<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Image\DeleteBlogImageAction;
use App\Repositories\Blog\OrphanBlogImageRepository;
use App\Services\Blog\Image\BlogImageStorage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menage des images du blog. Planifiee chaque jour (routes/console.php). Deux passes :
 *
 *  1. les images sans article depuis plus du delai de grace (envoyees puis jamais rattachees, retirees
 *     d'un article, ou restees apres une suppression d'article) : ligne et fichiers supprimes ;
 *  2. les dossiers presents sur le disque mais sans ligne en base (ecriture interrompue, suppression de
 *     fichiers qui avait echoue) : supprimes s'ils sont plus vieux que le delai de grace.
 *
 * Rejouable. Un element en echec est journalise et n'arrete pas les autres.
 *  --dry  liste ce qui serait supprime, sans rien supprimer
 */
final class PurgeOrphanBlogImages extends Command
{
    private const BATCH_SIZE = 200;

    protected $signature = 'festilaw:blog-purge-orphan-images {--dry : Simulation, sans suppression}';

    protected $description = 'Supprime les images du blog qui n\'appartiennent a aucun article et les dossiers d\'image sans ligne.';

    public function handle(
        OrphanBlogImageRepository $orphans,
        DeleteBlogImageAction $delete,
        BlogImageStorage $storage,
    ): int {
        $dry = (bool) $this->option('dry');
        $cutoff = CarbonImmutable::now()->subHours((int) config('blog.images.orphan_retention_hours', 24));
        $deleted = 0;
        $swept = 0;
        $failed = 0;

        foreach ($orphans->orphanIdsUntouchedSince($cutoff, self::BATCH_SIZE) as $imageId) {
            if ($dry) {
                $this->line("Would delete orphan image #{$imageId}.");
                $deleted++;

                continue;
            }

            try {
                $delete->execute($imageId) && $deleted++;
            } catch (Throwable $e) {
                $failed++;
                Log::error('Orphan blog image purge failed; it will be retried.', ['image_id' => $imageId, 'exception' => $e]);
            }
        }

        try {
            $known = array_flip($orphans->knownUuids());

            foreach ($storage->directories() as $uuid) {
                if (isset($known[$uuid])) {
                    continue;
                }

                $lastModified = $storage->lastModified($uuid);

                if ($lastModified !== null && $lastModified >= $cutoff->getTimestamp()) {
                    continue; // envoi peut-etre en cours : on laisse le delai de grace
                }

                if ($dry) {
                    $this->line("Would delete folder without image row: {$uuid}.");
                    $swept++;

                    continue;
                }

                $storage->deleteQuietly($uuid) ? $swept++ : $failed++;
            }
        } catch (Throwable $e) {
            $failed++;
            Log::error('Blog image folder sweep failed; it will be retried.', ['exception' => $e]);
        }

        $prefix = $dry ? 'Blog (dry run)' : 'Blog';
        $this->info("{$prefix}: {$deleted} orphan image(s) deleted, {$swept} folder(s) without row removed, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
