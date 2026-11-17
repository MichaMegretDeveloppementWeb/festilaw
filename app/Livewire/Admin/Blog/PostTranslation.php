<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\RequestBlogTranslationAction;
use App\Data\Blog\Generation\RequestBlogTranslationData;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\BlogTranslationResultService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Traduction assistee dans le formulaire d'article (tache 6.14). Le formulaire envoie le texte d'une langue
 * (evenement `request-post-translation`) ; la traduction part en generation, traitee par le worker. Ce
 * composant attend son resultat, puis le rend au formulaire (`post-translation-ready`), qui remplit les champs
 * et l'editeur de la langue visee sans rien enregistrer. Un echec le dit (`post-translation-failed`).
 */
class PostTranslation extends Component
{
    use HandlesAdminErrors;

    #[Locked]
    public ?int $postId = null;

    #[Locked]
    public ?int $generationId = null;

    #[Locked]
    public ?string $target = null;

    /**
     * @param  array<string, mixed>  $source  textes et document de la langue de depart, tels que dans le formulaire
     */
    #[On('request-post-translation')]
    public function request(string $from, string $to, array $source, RequestBlogTranslationAction $requestTranslation): void
    {
        if ($this->generationId !== null) {
            return;
        }

        try {
            $this->generationId = $requestTranslation->execute(new RequestBlogTranslationData(
                from: $from,
                to: $to,
                title: (string) ($source['title'] ?? ''),
                excerpt: (string) ($source['excerpt'] ?? ''),
                metaTitle: (string) ($source['metaTitle'] ?? ''),
                metaDescription: (string) ($source['metaDescription'] ?? ''),
                content: is_array($source['content'] ?? null) ? $source['content'] : null,
                postId: $this->postId,
                requestedBy: auth()->id(),
            ));
            $this->target = $to;
        } catch (BaseAppException $e) {
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');
            $this->dispatch('post-translation-failed', locale: $to);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'blog post translation request');
            $this->dispatch('post-translation-failed', locale: $to);
        }
    }

    /** Releve regulier tant qu'une traduction est attendue. */
    public function check(BlogTranslationResultService $results): void
    {
        if ($this->generationId === null) {
            return;
        }

        try {
            $result = $results->find($this->generationId);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'blog post translation check');

            return;
        }

        if ($result->isPending()) {
            return;
        }

        $target = (string) $this->target;
        $this->reset('generationId', 'target');

        if (! $result->isReady()) {
            $this->dispatch('ui-toast', title: __('La traduction n\'a pas abouti. Réessayez, et contactez l\'assistance technique si cela se reproduit.'), type: 'error');
            $this->dispatch('post-translation-failed', locale: $target);

            return;
        }

        $this->dispatch('post-translation-ready', locale: $result->locale, texts: $result->texts, content: $result->content);
        $this->dispatch('ui-toast', title: __('Traduction prête. Relisez-la, puis enregistrez l\'article.'), type: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.blog.post-translation');
    }
}
