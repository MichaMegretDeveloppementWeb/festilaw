<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Post\PublishBlogPostAction;
use App\Actions\Blog\Post\UnpublishBlogPostAction;
use App\Exceptions\Blog\BlogPostException;
use App\Repositories\Blog\DueScheduledBlogPostRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publie les articles programmes dont l'heure est passee. Planifiee chaque minute
 * (routes/console.php). Simple declencheur : la publication est faite par PublishBlogPostAction, qui
 * reverifie sous verrou que l'article est toujours complet.
 *
 * Rejouable : seuls les articles encore "programmes" sont pris. Un article qui n'est plus pret (image
 * supprimee depuis la programmation, par exemple) repasse en brouillon avec un avertissement au
 * journal, au lieu d'echouer a chaque minute. Un article en echec n'arrete pas les autres.
 */
final class PublishScheduledBlogPosts extends Command
{
    private const BATCH_SIZE = 50;

    protected $signature = 'festilaw:blog-publish-scheduled';

    protected $description = 'Publie les articles du blog programmes dont l\'heure est passee.';

    public function handle(
        DueScheduledBlogPostRepository $due,
        PublishBlogPostAction $publish,
        UnpublishBlogPostAction $unpublish,
    ): int {
        $published = 0;
        $reverted = 0;
        $failed = 0;

        foreach ($due->dueIds(CarbonImmutable::now(), self::BATCH_SIZE) as $postId) {
            try {
                $publish->execute($postId);
                $published++;
            } catch (BlogPostException $e) {
                $this->revertToDraft($unpublish, $postId, $e) ? $reverted++ : $failed++;
            } catch (Throwable $e) {
                $failed++;
                Log::error('Scheduled blog post publication failed; it will be retried.', ['post_id' => $postId, 'exception' => $e]);
            }
        }

        // Un passage sans rien a faire ne laisse aucune trace (la commande tourne chaque minute).
        if ($published + $reverted + $failed > 0) {
            $this->info("Blog: {$published} post(s) published, {$reverted} sent back to draft, {$failed} failed.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** L'article n'est plus publiable : on le sort de la programmation pour ne pas le reprendre chaque minute. */
    private function revertToDraft(UnpublishBlogPostAction $unpublish, int $postId, BlogPostException $reason): bool
    {
        try {
            $unpublish->execute($postId);

            Log::warning('Scheduled blog post could not be published and went back to draft.', [
                'post_id' => $postId,
                'reason' => $reason->getMessage(),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Scheduled blog post could neither be published nor sent back to draft.', ['post_id' => $postId, 'exception' => $e]);

            return false;
        }
    }
}
