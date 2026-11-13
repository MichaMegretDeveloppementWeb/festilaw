<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog;

use App\Actions\Blog\Post\PublishBlogPostAction;
use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Data\Admin\Blog\BlogPostStatusChangeResult;
use App\Data\Blog\SaveBlogPostData;
use App\Exceptions\BaseAppException;
use App\Services\Blog\Post\BlogPostReadinessCalculator;

/**
 * Bouton "Publier" du formulaire : enregistre ce qui est a l'ecran, puis publie si l'article est pret.
 * Deux cas d'usage enchaines, chacun dans sa propre transaction, et non une seule : un article pas
 * encore pret doit rester ENREGISTRE (en brouillon), avec la liste de ce qui bloque, au lieu de perdre
 * la saisie. Ce n'est donc pas une erreur, c'est une issue normale rendue par le resultat.
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
        // L'enregistrement rend l'article avec ses textes et ses images charges.
        $post = $this->save->execute($data);
        $readiness = $this->readiness->calculate($post);

        if (! $readiness->isReady()) {
            return BlogPostStatusChangeResult::notReady($post->id, $readiness);
        }

        $this->publish->execute($post->id);

        return BlogPostStatusChangeResult::done($post->id);
    }
}
