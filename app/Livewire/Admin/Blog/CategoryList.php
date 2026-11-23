<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Category\DeleteBlogCategoryAction;
use App\Actions\Blog\Category\ReorderBlogCategoriesAction;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\BlogCategoryAdminService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Back-office : categories du blog, dans leur ordre d'affichage. Glisser-deposer pour les ordonner,
 * suppression refusee tant qu'une categorie contient des articles. Aucune requete ici.
 */
#[Layout('layouts.admin')]
class CategoryList extends Component
{
    use HandlesAdminErrors;

    /** Appele par wire:sort : identifiant de la categorie deplacee et sa nouvelle place (a partir de 0). */
    public function reorder(int|string $categoryId, int|string $position, ReorderBlogCategoriesAction $reorder): void
    {
        try {
            $reorder->execute((int) $categoryId, (int) $position);
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin reorder blog categories');

            return;
        }

        $this->dispatch('ui-toast', title: __('Ordre des catégories enregistré.'), type: 'success');
    }

    public function delete(int $categoryId, DeleteBlogCategoryAction $deleteCategory): void
    {
        try {
            $deleteCategory->execute($categoryId);
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin delete blog category');

            return;
        }

        $this->dispatch('ui-toast', title: __('Catégorie supprimée.'), type: 'success');
    }

    public function render(BlogCategoryAdminService $categories): View
    {
        $rows = null;

        try {
            $rows = $categories->list();
            $this->resetErrorBag('categories-load-failed');
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the admin blog category list.', ['exception' => $e]);
            $this->addError('categories-load-failed', __('Le chargement des catégories a échoué. Réessayez. Si le problème persiste, contactez l\'assistance technique.'));
        }

        return view('livewire.admin.blog.category-list', [
            'categories' => $rows ?? [],
        ])->title(__('Catégories'))->layoutData(['surface' => 'plain']);
    }
}
