<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Data\Blog\Generation\RequestBlogFeaturedImageData;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogEditorialException;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogImage;
use App\Repositories\Blog\BlogImageRequestRepository;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogAiSettingsService;

/**
 * Une image principale demandee depuis un article (demande de Micha du 24/11/2026) : une nouvelle recherche dans
 * la banque d'images, ou une image creee par l'IA, quand la generation n'en a pas trouve ou que la sienne ne
 * convient pas, et pour un article ecrit a la main. La demande part en generation de type `image`, traitee par le
 * worker comme toute generation, jamais dans la requete web. Son cout est estime et borne par l'arret de
 * securite, et compte dans la depense du mois. Rien n'est enregistre sur l'article : l'image revient dans le
 * formulaire, qui la rattache a l'enregistrement.
 */
final readonly class RequestBlogFeaturedImageAction
{
    /** @param  list<string>  $locales  langues du site, dans l'ordre ou chercher un titre (anglais d'abord) */
    public function __construct(
        private QueueBlogGenerationAction $queue,
        private BlogAiSettingsService $settings,
        private BlogImageRequestRepository $requests,
        private array $locales,
    ) {}

    /**
     * @return int la generation creee
     *
     * @throws BlogEditorialException quand la demande n'a pas de sens (aucun titre, aucun modele d'image)
     * @throws BlogGenerationException quand la configuration du pipeline ne permet pas de generer
     */
    public function execute(RequestBlogFeaturedImageData $data): int
    {
        if ($data->mode === BlogAiImageMode::None) {
            throw BlogEditorialException::unsupportedImageRequest($data->mode->value);
        }

        if ($data->mode === BlogAiImageMode::Generated && blank($this->settings->current()->modelFor(BlogAiModelCatalog::IMAGE_SETTING))) {
            throw BlogEditorialException::noImageModelChosen();
        }

        // La langue dont le titre et l'extrait decrivent l'article : l'anglais d'abord, la langue de reference du blog.
        $locale = null;

        foreach ($this->locales as $candidate) {
            if (trim($data->titles[$candidate] ?? '') !== '' || trim($data->excerpts[$candidate] ?? '') !== '') {
                $locale = $candidate;

                break;
            }
        }

        if ($locale === null) {
            throw BlogEditorialException::nothingToIllustrate();
        }

        $title = trim($data->titles[$locale] ?? '');
        // Dans l'historique, la demande porte le titre francais, comme les articles et leurs generations (Micha,
        // 24/11/2026) : on y cherche un article sous ce titre. A defaut, celui qui decrit l'article.
        $frenchTitle = trim($data->titles['fr'] ?? '');
        $label = $frenchTitle !== '' ? $frenchTitle : ($title !== '' ? $title : __('article sans titre'));
        // L'image actuelle, lue en base et non dans le navigateur : sa description pour en demander une autre, et la
        // page de la banque d'ou elle vient, pour ne pas la reprendre.
        $current = $data->currentImageId === null ? null : BlogImage::query()->find($data->currentImageId, ['id', 'alt', 'credit_url']);

        return $this->queue->execute(new QueueBlogGenerationData(
            type: BlogGenerationType::Image,
            topicHint: $data->mode === BlogAiImageMode::Stock
                ? __('Image principale de « :title », dans la banque d\'images', ['title' => $label])
                : __('Image principale de « :title », créée par l\'IA', ['title' => $label]),
            requestedBy: $data->requestedBy,
            payload: ['image_request' => [
                'post_id' => $data->postId,
                'locale' => $locale,
                'title' => $title,
                'excerpt' => trim($data->excerpts[$locale] ?? ''),
                'current_alt' => $current?->altFor('en') ?? '',
                'current_credit_url' => (string) ($current->credit_url ?? ''),
                // Les photos deja obtenues pour l'article ne sont pas reprises : une nouvelle demande en veut une autre,
                // et la meme photo paraissait deux fois dans « Images déjà obtenues » (24/11/2026).
                'obtained_credit_urls' => $data->postId === null ? [] : $this->requests->obtainedCreditUrls($data->postId),
            ]],
            images: new GenerationImageOptions($data->mode, 1),
            sourcePostId: $data->postId,
        ));
    }
}
