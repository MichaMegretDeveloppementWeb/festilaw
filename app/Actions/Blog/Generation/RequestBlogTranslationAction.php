<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Data\Blog\Generation\RequestBlogTranslationData;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogEditorialException;
use App\Exceptions\Blog\BlogGenerationException;
use App\Services\Blog\Content\BlogDocumentMarkdownWriter;

/**
 * Traduction assistee d'un article (tache 6.14) : le texte d'une langue du formulaire part en generation de
 * type `translation`, traitee par le worker comme toute generation (jamais dans la requete web). Le texte est
 * donne au modele en Markdown, ses images en emplacements numerotes avec leurs attributs gardes a part. Le
 * plafond de depense s'applique. Rien n'est enregistre sur l'article : le resultat revient dans le formulaire.
 */
final readonly class RequestBlogTranslationAction
{
    /** Nom de chaque langue, pour le libelle de la generation au journal. */
    private const LANGUAGES = ['en' => 'anglais', 'fr' => 'français', 'es' => 'espagnol'];

    /** @param  list<string>  $locales */
    public function __construct(
        private QueueBlogGenerationAction $queue,
        private BlogDocumentMarkdownWriter $markdown,
        private array $locales,
    ) {}

    /**
     * @return int la generation creee
     *
     * @throws BlogEditorialException quand la demande n'a pas de sens
     * @throws BlogGenerationException quand le plafond de depense est atteint
     */
    public function execute(RequestBlogTranslationData $data): int
    {
        if ($data->from === $data->to || ! in_array($data->from, $this->locales, true) || ! in_array($data->to, $this->locales, true)) {
            throw BlogEditorialException::unsupportedTranslation($data->from, $data->to);
        }

        ['markdown' => $body, 'images' => $images] = $this->markdown->write($data->content);

        if (trim($data->title) === '' && trim($body) === '') {
            throw BlogEditorialException::nothingToTranslate($data->from);
        }

        return $this->queue->execute(new QueueBlogGenerationData(
            type: BlogGenerationType::Translation,
            topicHint: __('Traduction en :language de « :title »', [
                'language' => self::LANGUAGES[$data->to] ?? $data->to,
                'title' => trim($data->title) !== '' ? trim($data->title) : __('article sans titre'),
            ]),
            requestedBy: $data->requestedBy,
            payload: ['translation' => [
                'post_id' => $data->postId,
                'from' => $data->from,
                'to' => $data->to,
                'article' => [
                    'title' => trim($data->title),
                    'excerpt' => trim($data->excerpt),
                    'meta_title' => trim($data->metaTitle),
                    'meta_description' => trim($data->metaDescription),
                    'body' => $body,
                ],
                'images' => $images,
            ]],
            images: new GenerationImageOptions(BlogAiImageMode::None, 1),
        ));
    }
}
