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
 * donne au modele en Markdown, ses images en emplacements numerotes avec leurs attributs gardes a part. Son
 * appel reserve son cout sous son arret de securite. Rien n'est enregistre sur l'article : le resultat
 * revient dans le formulaire.
 *
 * Un seul champ peut aussi partir seul (demande de Micha du 24/11/2026) : apres une retouche ciblee, retraduire
 * toute la langue couterait pour rien. Seul ce champ est alors donne au modele, et son cout suit sa taille.
 */
final readonly class RequestBlogTranslationAction
{
    /** Nom de chaque langue, pour le libelle de la generation au journal. */
    private const LANGUAGES = ['en' => 'anglais', 'fr' => 'français', 'es' => 'espagnol'];

    /** Les champs qui se traduisent seuls, et leur nom au journal. */
    private const FIELDS = [
        'title' => 'titre',
        'excerpt' => 'extrait',
        'metaTitle' => 'titre pour Google',
        'metaDescription' => 'description pour Google',
        'content' => 'contenu',
    ];

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
     * @throws BlogGenerationException quand la configuration du pipeline ne permet pas de generer
     */
    public function execute(RequestBlogTranslationData $data): int
    {
        if ($data->from === $data->to || ! in_array($data->from, $this->locales, true) || ! in_array($data->to, $this->locales, true)
            || ($data->field !== null && ! isset(self::FIELDS[$data->field]))) {
            throw BlogEditorialException::unsupportedTranslation($data->from, $data->to);
        }

        $wanted = fn (string $field): bool => $data->field === null || $data->field === $field;
        ['markdown' => $body, 'images' => $images] = $wanted('content') ? $this->markdown->write($data->content) : ['markdown' => '', 'images' => []];
        $article = [
            'title' => $wanted('title') ? trim($data->title) : '',
            'excerpt' => $wanted('excerpt') ? trim($data->excerpt) : '',
            'meta_title' => $wanted('metaTitle') ? trim($data->metaTitle) : '',
            'meta_description' => $wanted('metaDescription') ? trim($data->metaDescription) : '',
            'body' => $body,
        ];

        if ($data->field !== null && implode('', $article) === '') {
            throw BlogEditorialException::nothingToTranslateInField($data->from, $data->field);
        }

        if ($data->field === null && $article['title'] === '' && trim($body) === '') {
            throw BlogEditorialException::nothingToTranslate($data->from);
        }

        // Dans l'historique, la demande porte le titre francais, comme les articles (Micha, 24/11/2026), a defaut
        // celui de la langue de depart.
        $title = trim($data->frenchTitle) !== '' ? trim($data->frenchTitle) : trim($data->title);
        $title = $title !== '' ? $title : __('article sans titre');

        return $this->queue->execute(new QueueBlogGenerationData(
            type: BlogGenerationType::Translation,
            topicHint: $data->field === null
                ? __('Traduction en :language de « :title »', ['language' => self::LANGUAGES[$data->to] ?? $data->to, 'title' => $title])
                : __('Traduction en :language du champ « :field » de « :title »', ['language' => self::LANGUAGES[$data->to] ?? $data->to, 'field' => self::FIELDS[$data->field], 'title' => $title]),
            requestedBy: $data->requestedBy,
            payload: ['translation' => [
                'post_id' => $data->postId,
                'from' => $data->from,
                'to' => $data->to,
                'field' => $data->field,
                'article' => $article,
                'images' => $images,
            ]],
            images: new GenerationImageOptions(BlogAiImageMode::None, 1),
        ));
    }
}
