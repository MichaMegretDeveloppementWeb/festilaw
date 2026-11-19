{{-- Liste des categories, sur page blanche et sur toute la largeur. `pending` : la categorie visee par la
     fenetre de suppression. Lignes et filets partent du bord du titre de la page, comme toutes les listes
     (decision de Micha du 19/11). --}}
<div x-data="{ pending: { id: null, name: '' } }">
    <x-ui::page-header :title="__('Catégories')" :description="__('Les rubriques du blog. Leur ordre est celui du menu des catégories sur le site.')" class="mb-8">
        <x-ui::button :href="route('admin.blog.categories.create')"><x-ui::icon name="plus" /> {{ __('Nouvelle catégorie') }}</x-ui::button>
    </x-ui::page-header>

    @error('categories-load-failed')
        <x-ui::load-failure :title="__('Le chargement des catégories a échoué.')"
            :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
    @else
        <div class="border-y border-default">
            @if ($categories === [])
                <x-ui::empty-state icon="tag" :title="__('Aucune catégorie pour le moment.')"
                    :description="__('Un article a besoin d\'une catégorie pour être publié.')">
                    <x-ui::button :href="route('admin.blog.categories.create')"><x-ui::icon name="plus" /> {{ __('Nouvelle catégorie') }}</x-ui::button>
                </x-ui::empty-state>
            @else
                <ul wire:sort="reorder" class="divide-y divide-[var(--ui-border)]">
                    @foreach ($categories as $category)
                        @php($name = $category->names['fr'] ?? ($category->names['en'] ?? __('Sans nom')))
                        @php($editUrl = route('admin.blog.categories.edit', ['category' => $category->id]))
                        {{-- Toute la ligne ouvre la categorie ; son nom reste un vrai lien pour le clavier. --}}
                        <li wire:key="category-{{ $category->id }}" wire:sort:item="{{ $category->id }}"
                            x-on:click="if (! $event.target.closest('a, button, [data-handle]')) window.location.href = '{{ $editUrl }}'"
                            class="flex items-center gap-3 py-4 transition-colors hover:bg-elevated">
                            <span wire:sort:handle data-handle class="-ml-1.5 flex shrink-0 cursor-grab items-center rounded-lg p-1.5 text-muted transition hover:text-secondary" title="{{ __('Glisser pour changer l\'ordre') }}">
                                <x-ui::icon name="bars-2" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $editUrl }}" class="block truncate text-[14px] font-medium text-primary">{{ $name }}</a>
                                <p class="truncate text-[12px] text-muted">
                                    EN · {{ $category->names['en'] ?? '-' }}
                                    <span class="mx-1">/</span>
                                    ES · {{ $category->names['es'] ?? '-' }}
                                </p>
                            </div>
                            <x-ui::badge :color="$category->postCount > 0 ? 'blue' : 'gray'" class="shrink-0">
                                {{ trans_choice('{0} Aucun article|{1} 1 article|[2,*] :count articles', $category->postCount, ['count' => $category->postCount]) }}
                            </x-ui::badge>
                            <button type="button" wire:sort:ignore
                                x-on:click="pending = { id: {{ $category->id }}, name: @js($name) }; $dispatch('ui-open-modal', 'delete-category')"
                                class="-mr-1 flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-muted transition hover:text-red-700 sm:min-h-0 sm:min-w-0 sm:p-1.5 dark:hover:text-red-400"
                                aria-label="{{ __('Supprimer la catégorie :name', ['name' => $name]) }}" title="{{ __('Supprimer') }}">
                                <x-ui::icon name="trash" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <x-ui::modal name="delete-category" variant="confirm" :title="__('Supprimer cette catégorie ?')">
            <p class="font-medium text-primary" x-text="pending.name"></p>
            <p>{{ __('La suppression est définitive. Elle est refusée tant que la catégorie contient des articles.') }}</p>

            <x-slot:actions>
                <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'delete-category')">{{ __('Annuler') }}</x-ui::button>
                <x-ui::button variant="danger" x-on:click="$wire.delete(pending.id); $dispatch('ui-close-modal', 'delete-category')">{{ __('Supprimer') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::modal>
    @enderror
</div>
