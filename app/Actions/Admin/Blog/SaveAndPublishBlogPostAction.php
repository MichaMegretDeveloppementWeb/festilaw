<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog;

use App\Actions\Blog\Post\PublishBlogPostAction;
use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Data\Admin\Blog\BlogPostStatusChangeResult;
use App\Data\Blog\SaveBlogPostData;
use App\Exceptions\BaseAppException;
use App\Services\Blog\Post\BlogPostReadinessCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Bouton "Publier" du formulaire : enregistre ce qui est a l'ecran, puis publie si l'article est pret, dans une
 * seule transaction (audit du 23/11). Un article pas encore pret reste ENREGISTRE en brouillon, avec la liste
 * de ce qui bloque : c'est une issue normale rendue par le resultat, la transaction est validee. En revanche,
 * une publication qui echoue annule aussi l'enregistrement : l'ecran dit « pas enregistre » et dit vrai, et le
 * clic suivant ne cree pas un second article.
 */
final readonly class SaveAndPublishBlogPostAction
{
    public function __construct(
        private SaveBlogPostAction $save,
        private PublishBlogPostAction $publish,
        private BlogPostReadinessCalculator $readiness,
    ) {}

    /**
     * @throws BaseAppException si l'enregistrement est refuse (adresse prise, contenu invalide...)
     */
    public function execute(SaveBlogPostData $data): BlogPostStatusChangeResult
    {
        return DB::transaction(function () use ($data): BlogPostStatusChangeResult {
            // L'enregistrement rend l'article avec ses textes et ses images charges.
            $post = $this->save->execute($data);
            $readiness = $this->readiness->calculate($post);

            if (! $readiness->isReady()) {
                return BlogPostStatusChangeResult::notReady($post->id, $readiness);
            }

            $this->publish->execute($post->id);

            return BlogPostStatusChangeResult::done($post->id);
        });
    }
}
