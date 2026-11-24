<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\RequestBlogFeaturedImageAction;
use App\Data\Blog\Generation\BlogFeaturedImageResult;
use App\Data\Blog\Generation\RequestBlogFeaturedImageData;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\BlogFeaturedImageResultService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Image principale demandee depuis le formulaire d'article (demande de Micha du 24/11/2026). Le formulaire envoie
 * la source voulue (banque ou IA), les titres et extraits, et l'image actuelle (evenement `request-featured-image`) ;
 * la demande part en generation, traitee par le worker. Ce composant attend son resultat, puis le rend au
 * formulaire : `featured-image-ready` (l'image, que le formulaire pose par PostForm::useFoundImage) ou
 * `featured-image-failed` (le mode a proposer a la place). Meme forme que PostTranslation.
 */
class PostFeaturedImage extends Component
{
    use HandlesAdminErrors;

    #[Locked]
    public ?int $postId = null;

    #[Locked]
    public ?int $generationId = null;

    #[Locked]
    public ?string $mode = null;

    /** Depuis quand le formulaire attend (horodatage), pour cesser d'attendre une demande trop longue. */
    #[Locked]
    public ?int $waitingSince = null;

    /** Au-dela, le formulaire cesse d'attendre : la demande continue, la page rechargee la retrouvera. */
    private const MAX_WAIT_SECONDS = 1200;

    /** Une demande faite avant un rechargement de la page est retrouvee et attendue de nouveau. */
    public function mount(BlogFeaturedImageResultService $results): void
    {
        if ($this->postId === null) {
            return;
        }

        try {
            $pending = $results->pendingForPost($this->postId);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'blog featured image resume');

            return;
        }

        if ($pending !== null) {
            $this->generationId = $pending['generation_id'];
            $this->mode = $pending['mode'];
            $this->waitingSince = now()->getTimestamp();
            $this->dispatch('featured-image-pending', mode: $this->mode);
        }
    }

    /**
     * @param  array<string, string>  $titles  titre par langue, tel que dans le formulaire
     * @param  array<string, string>  $excerpts  extrait par langue
     * @param  int|null  $currentImageId  l'image principale actuelle du formulaire, s'il y en a une
     */
    #[On('request-featured-image')]
    public function request(string $mode, array $titles, array $excerpts, RequestBlogFeaturedImageAction $requestImage, ?int $currentImageId = null): void
    {
        if ($this->generationId !== null) {
            return;
        }

        $imageMode = BlogAiImageMode::tryFrom($mode) ?? BlogAiImageMode::None;

        try {
            $this->generationId = $requestImage->execute(new RequestBlogFeaturedImageData(
                mode: $imageMode,
                titles: array_map(static fn (mixed $title): string => (string) $title, $titles),
                excerpts: array_map(static fn (mixed $excerpt): string => (string) $excerpt, $excerpts),
                currentImageId: $currentImageId,
                postId: $this->postId,
                requestedBy: auth()->id(),
            ));
            $this->mode = $imageMode->value;
            $this->waitingSince = now()->getTimestamp();
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');
            $this->dispatch('featured-image-failed');
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'blog featured image request');
            $this->dispatch('featured-image-failed');
        }
    }

    /** Releve regulier tant qu'une image est attendue, 20 minutes au plus. */
    public function check(BlogFeaturedImageResultService $results): void
    {
        if ($this->generationId === null) {
            return;
        }

        try {
            $result = $results->find($this->generationId);
        } catch (Throwable $e) {
            // Le releve s'arrete apres une erreur, sans repeter la notification toutes les deux secondes. La demande
            // continue, et la page rechargee la retrouve.
            $this->reportAdminError($e, 'blog featured image check');
            $this->reset('generationId', 'mode', 'waitingSince');
            $this->dispatch('featured-image-failed');

            return;
        }

        if ($result->isPending()) {
            if (now()->getTimestamp() - (int) $this->waitingSince > self::MAX_WAIT_SECONDS) {
                $this->reset('generationId', 'mode', 'waitingSince');
                $this->dispatch('ui-toast', title: __('La recherche d\'image prend plus de temps que prévu. Elle continue, et vous la retrouverez en rechargeant la page un peu plus tard.'), type: 'warning');
                $this->dispatch('featured-image-failed');
            }

            return;
        }

        $this->reset('generationId', 'mode', 'waitingSince');

        if ($result->isReady()) {
            $this->dispatch('featured-image-ready', imageId: $result->imageId);

            return;
        }

        $stock = $result->mode === BlogAiImageMode::Stock->value;
        $this->dispatch('ui-toast', type: 'warning', title: match (true) {
            $stock && $result->cause === BlogFeaturedImageResult::NONE_FIT => __('Aucune photo de la banque ne convenait à cet article. Relancez la recherche, ou choisissez « Générée par IA ».'),
            $stock && $result->cause === BlogFeaturedImageResult::BANK_UNAVAILABLE => __('La banque d\'images n\'a pas répondu. Réessayez plus tard, ou choisissez « Générée par IA ».'),
            default => __('L\'image n\'a pas pu être préparée. Réessayez, et contactez l\'assistance technique si cela se reproduit.'),
        });
        $this->dispatch('featured-image-failed');
    }

    public function render(): View
    {
        return view('livewire.admin.blog.post-featured-image');
    }
}
