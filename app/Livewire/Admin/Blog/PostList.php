<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Post\DeleteBlogPostAction;
use App\Data\Admin\Blog\BlogPostListFilters;
use App\Enums\Blog\BlogPostListSort;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\BlogPostListService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Back-office : liste des articles du blog (recherche, filtres, tri, suppression). Aucune requete ici :
 * la lecture passe par BlogPostListService, la suppression par DeleteBlogPostAction.
 */
#[Layout('layouts.admin')]
class PostList extends Component
{
    use HandlesAdminErrors;
    use WithPagination;

    /** Nombres d'articles par page proposes par le pied de la liste. Le premier est le defaut. */
    public const PER_PAGE_CHOICES = [15, 25, 50, 100];

    #[Url]
    public string $search = '';

    /** '' (tous) ou une valeur de BlogPostStatus. */
    #[Url]
    public string $status = '';

    /** '' (toutes) ou l'identifiant d'une categorie. */
    #[Url]
    public string $category = '';

    /** '' (toutes) ou une valeur de BlogPostOrigin. */
    #[Url]
    public string $origin = '';

    /** Valeur de BlogPostListSort. */
    #[Url]
    public string $sort = BlogPostListSort::Recent->value;

    /** 'asc' ou 'desc'. */
    #[Url]
    public string $direction = 'desc';

    #[Url]
    public int $perPage = self::PER_PAGE_CHOICES[0];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'category', 'origin', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Clic sur un en-tete : ordre croissant, puis decroissant, puis retour au tri par defaut (les plus
     * recents d'abord).
     */
    public function sortBy(string $column): void
    {
        $sort = BlogPostListSort::tryFrom($column);

        if ($sort === null) {
            return;
        }

        if ($this->sort !== $sort->value) {
            $this->sort = $sort->value;
            $this->direction = 'asc';
        } elseif ($this->direction === 'asc') {
            $this->direction = 'desc';
        } else {
            $this->sort = BlogPostListSort::Recent->value;
            $this->direction = 'desc';
        }

        $this->resetPage();
    }

    public function delete(int $postId, DeleteBlogPostAction $deletePost): void
    {
        try {
            $deletePost->execute($postId);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin delete blog post');

            return;
        }

        $this->dispatch('ui-toast', title: __('Article supprimé.'), type: 'success');
    }

    public function render(BlogPostListService $posts): View
    {
        $result = null;
        // Une valeur inconnue dans l'adresse ne casse rien : elle retombe sur le defaut.
        $sort = BlogPostListSort::tryFrom($this->sort) ?? BlogPostListSort::Recent;
        $perPage = in_array($this->perPage, self::PER_PAGE_CHOICES, true) ? $this->perPage : self::PER_PAGE_CHOICES[0];

        try {
            $result = $posts->list(
                new BlogPostListFilters(
                    search: $this->search,
                    status: BlogPostStatus::tryFrom($this->status)?->value ?? '',
                    categoryId: ctype_digit($this->category) ? (int) $this->category : null,
                    origin: BlogPostOrigin::tryFrom($this->origin)?->value ?? '',
                    sort: $sort,
                    descending: $this->direction !== 'asc',
                ),
                $perPage,
            );
            $this->resetErrorBag('posts-load-failed');
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the admin blog post list.', ['exception' => $e]);
            $this->addError('posts-load-failed', __('Le chargement des articles a échoué. Réessayez ; si le problème persiste, contactez-nous.'));
        }

        return view('livewire.admin.blog.post-list', [
            'posts' => $result?->posts,
            'categoryOptions' => $result?->categoryOptions ?? [],
            'statuses' => BlogPostStatus::cases(),
            'origins' => BlogPostOrigin::cases(),
            'sorted' => $sort,
            'sortDirection' => $this->direction === 'asc' ? 'asc' : 'desc',
            'perPageShown' => $perPage,
            'perPageChoices' => self::PER_PAGE_CHOICES,
            'activeFilters' => count(array_filter([$this->status, $this->category, $this->origin], static fn (string $value): bool => $value !== '')),
        ])->title(__('Articles'));
    }
}
