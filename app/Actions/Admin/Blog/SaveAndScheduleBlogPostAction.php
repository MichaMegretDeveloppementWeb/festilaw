<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog;

use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Actions\Blog\Post\ScheduleBlogPostAction;
use App\Data\Admin\Blog\BlogPostStatusChangeResult;
use App\Data\Blog\SaveBlogPostData;
use App\Exceptions\BaseAppException;
use App\Exceptions\Blog\BlogPostException;
use App\Services\Blog\Post\BlogPostReadinessCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bouton "Programmer" du formulaire : enregistre ce qui est a l'ecran, puis programme la publication
 * si l'article est pret, dans une seule transaction. Meme principe que SaveAndPublishBlogPostAction : un
 * article pas encore pret reste enregistre en brouillon, et le resultat dit ce qui bloque ; une
 * programmation qui echoue annule aussi l'enregistrement.
 */
final readonly class SaveAndScheduleBlogPostAction
{
    public function __construct(
        private SaveBlogPostAction $save,
        private ScheduleBlogPostAction $schedule,
        private BlogPostReadinessCalculator $readiness,
    ) {}

    /**
     * @param  CarbonImmutable  $at  moment de publication (UTC), dans le futur
     *
     * @throws BaseAppException si l'enregistrement est refuse, si la date est passee ou si l'article est deja publie
     */
    public function execute(SaveBlogPostData $data, CarbonImmutable $at): BlogPostStatusChangeResult
    {
        // Refus AVANT d'enregistrer : une date passee ne doit pas laisser un brouillon cree a moitie
        // (pour un article nouveau, l'ecran ne connaitrait pas encore son identifiant).
        if (! $at->isFuture()) {
            throw BlogPostException::scheduleNotInFuture($data->postId ?? 0, $at);
        }

        return DB::transaction(function () use ($data, $at): BlogPostStatusChangeResult {
            $post = $this->save->execute($data);
            $readiness = $this->readiness->calculate($post);

            if (! $readiness->isReady()) {
                return BlogPostStatusChangeResult::notReady($post->id, $readiness);
            }

            $this->schedule->execute($post->id, $at);

            return BlogPostStatusChangeResult::done($post->id);
        });
    }
}
