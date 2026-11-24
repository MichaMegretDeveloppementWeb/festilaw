<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\MarkBlogRequestDeliveredAction;
use App\Actions\Blog\Generation\RequestBlogTranslationAction;
use App\Data\Blog\Generation\RequestBlogTranslationData;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\BlogTranslationResultService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
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

    /** Depuis quand le formulaire attend (horodatage), pour cesser d'attendre une traduction trop longue. */
    #[Locked]
    public ?int $waitingSince = null;

    /** Au-dela, le formulaire cesse d'attendre : la traduction continue, la page rechargee la retrouvera. */
    private const MAX_WAIT_SECONDS = 1200;

    /**
     * Une traduction demandee avant un rechargement de la page est retrouvee et attendue de nouveau (controle du
     * 20/11) : sinon son resultat etait perdu. Finie pendant l'absence, elle est remise au retour, une seule fois
     * (Micha, 24/11/2026).
     */
    public function mount(BlogTranslationResultService $results, MarkBlogRequestDeliveredAction $delivered): void
    {
        if ($this->postId === null) {
            return;
        }

        try {
            $waiting = $results->undeliveredForPost($this->postId);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'blog post translation resume');

            return;
        }

        if ($waiting === null) {
            return;
        }

        $this->generationId = $waiting['generation_id'];
        $this->target = $waiting['locale'];
        $this->waitingSince = now()->getTimestamp();

        if ($waiting['finished']) {
            $this->check($results, $delivered);
        }
    }

    /**
     * @param  array<string, mixed>  $source  textes et document de la langue de depart, tels que dans le formulaire
     * @param  string|null  $field  un seul champ a traduire, ou nul pour toute la langue (Micha, 24/11/2026)
     */
    #[On('request-post-translation')]
    public function request(string $from, string $to, array $source, RequestBlogTranslationAction $requestTranslation, ?string $field = null): void
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
                field: $field,
            ));
            $this->target = $to;
            $this->waitingSince = now()->getTimestamp();

            // Un article neuf n'a pas encore d'identifiant : son premier enregistrement se rattachera la demande.
            if ($this->postId === null) {
                $this->dispatch('blog-request-queued', generationId: $this->generationId)->to(PostForm::class);
            }
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');
            $this->dispatch('post-translation-failed', locale: $to);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'blog post translation request');
            $this->dispatch('post-translation-failed', locale: $to);
        }
    }

    /** Releve regulier tant qu'une traduction est attendue, 20 minutes au plus. Un resultat rendu est marque recu. */
    public function check(BlogTranslationResultService $results, MarkBlogRequestDeliveredAction $delivered): void
    {
        if ($this->generationId === null) {
            return;
        }

        $target = (string) $this->target;

        try {
            $result = $results->find($this->generationId);
        } catch (Throwable $e) {
            // Le releve s'arrete apres une erreur : sinon la meme notification revenait toutes les deux secondes
            // (audit du 23/11). La traduction continue, et la page rechargee la retrouve.
            $this->reportAdminError($e, 'blog post translation check');
            $this->reset('generationId', 'target', 'waitingSince');
            $this->dispatch('post-translation-failed', locale: $target);

            return;
        }

        if ($result->isPending()) {
            if (now()->getTimestamp() - (int) $this->waitingSince > self::MAX_WAIT_SECONDS) {
                $this->reset('generationId', 'target', 'waitingSince');
                $this->dispatch('ui-toast', title: __('La traduction prend plus de temps que prévu. Elle continue, et vous la retrouverez en rechargeant la page un peu plus tard.'), type: 'warning');
                $this->dispatch('post-translation-failed', locale: $target);
            }

            return;
        }

        try {
            $delivered->execute((int) $this->generationId);
        } catch (Throwable $e) {
            // Au pire, la traduction serait remise une seconde fois : elle se rend quand meme.
            Log::error('Blog translation request could not be marked delivered.', ['generation_id' => $this->generationId, 'exception' => $e]);
        }

        $this->reset('generationId', 'target', 'waitingSince');

        if (! $result->isReady()) {
            $this->dispatch('ui-toast', title: __('La traduction n\'a pas abouti. Réessayez, et contactez l\'assistance technique si cela se reproduit.'), type: 'error');
            $this->dispatch('post-translation-failed', locale: $target);

            return;
        }

        $this->dispatch('post-translation-ready', locale: $result->locale, texts: $result->texts, content: $result->content, field: $result->field);
        $this->dispatch('ui-toast', title: __('Traduction prête. Relisez-la, puis enregistrez l\'article.'), type: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.blog.post-translation');
    }
}
