{{-- Liste des articles. `pending` : l'article vise par la fenetre de suppression (donne par le menu de la ligne). --}}
<div x-data="{ filtersOpen: false, pending: { id: null, title: '', published: false } }">
    <x-ui::page-header :title="__('Articles')" :description="__('Les articles du blog, dans les trois langues.')" class="mb-6">
        <x-ui::button :href="route('admin.blog.posts.create')"><x-ui::icon name="plus" /> {{ __('Nouvel article') }}</x-ui::button>
    </x-ui::page-header>

    {{-- Recherche et filtres. Sur un ecran large, tout tient sur une ligne. En dessous, le bouton « Filtres »
         ouvre un panneau flottant, qui passe au-dessus de la liste sans la deplacer. Un seul jeu de champs
         dans les deux cas : le meme bloc est une ligne ou un panneau selon la largeur. --}}
    <div class="mb-4 flex items-center gap-3">
        <x-ui::search-input id="f-search" wire:model.live.debounce.400ms="search" wrapper-class="min-w-0 flex-1"
            :placeholder="__('Rechercher un titre')" aria-label="{{ __('Rechercher un article par son titre, dans les trois langues') }}"
            title="{{ __('La recherche porte sur le titre, dans les trois langues.') }}" />

        <div class="relative shrink-0 lg:static" x-on:click.outside="filtersOpen = false" x-on:keydown.escape.window="filtersOpen = false">
            <x-ui::button variant="secondary" class="lg:hidden" x-on:click="filtersOpen = ! filtersOpen"
                x-bind:aria-expanded="filtersOpen.toString()" aria-controls="post-filters" aria-haspopup="dialog">
                <x-ui::icon name="adjustments-horizontal" />
                <span>{{ __('Filtres') }}</span>
                @if ($activeFilters > 0)
                    <span class="flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-gray-900 px-1 text-[10px] font-semibold text-white dark:bg-white dark:text-gray-900">{{ $activeFilters }}</span>
                @endif
            </x-ui::button>

            <div id="post-filters" x-bind:data-open="filtersOpen.toString()"
                class="absolute right-0 top-full z-30 mt-2 hidden w-72 max-w-[calc(100vw-2rem)] flex-col gap-3 rounded-xl bg-surface p-4 shadow-[0_10px_40px_-8px_rgb(0_0_0/0.25)] data-[open=true]:flex lg:static lg:z-auto lg:mt-0 lg:flex lg:w-auto lg:max-w-none lg:flex-row lg:rounded-none lg:bg-transparent lg:p-0 lg:shadow-none dark:ring-1 dark:ring-white/10 lg:dark:ring-0">
                <p class="text-[13px] font-semibold text-primary lg:hidden">{{ __('Filtres') }}</p>
                <x-ui::select wire:model.live="status" class="lg:w-44" aria-label="{{ __('Filtrer par statut') }}"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->prepend(__('Tous les statuts'), '')->all()" />
                <x-ui::select wire:model.live="category" class="lg:w-52" aria-label="{{ __('Filtrer par catégorie') }}"
                    :options="collect($categoryOptions)->prepend(__('Toutes les catégories'), '')->all()" />
                <x-ui::select wire:model.live="origin" class="lg:w-44" aria-label="{{ __('Filtrer par origine') }}"
                    :options="collect($origins)->mapWithKeys(fn ($o) => [$o->value => $o->label()])->prepend(__('Toutes les origines'), '')->all()" />
                @if ($activeFilters > 0)
                    <button type="button" wire:click="clearFilters" data-clear-filters
                        class="self-start text-[12px] font-medium text-secondary underline underline-offset-2 hover:text-primary lg:hidden">{{ __('Réinitialiser les filtres') }}</button>
                @endif
            </div>
        </div>
    </div>

    @error('posts-load-failed')
        <x-ui::card :padding="false">
            <x-ui::load-failure :title="__('Le chargement des articles a échoué.')"
                :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
        </x-ui::card>
    @else
        <x-ui::card :padding="false" class="relative">
            {{-- Voile pendant qu'une recherche, un tri ou un changement de page se charge. --}}
            <div wire:loading.delay.long wire:target="search, status, category, origin, perPage, sortBy, gotoPage, nextPage, previousPage, delete"
                class="absolute inset-0 z-10 rounded-xl bg-surface/70">
                <div class="flex h-full items-center justify-center"><x-ui::spinner size="lg" /></div>
            </div>

            @if ($posts === null || $posts->isEmpty())
                @if ($search !== '' || $activeFilters > 0)
                    <x-ui::empty-state icon="magnifying-glass" :title="__('Aucun article ne correspond.')"
                        :description="__('Modifiez la recherche ou les filtres.')" />
                @else
                    <x-ui::empty-state icon="newspaper" :title="__('Aucun article pour le moment.')">
                        <x-ui::button :href="route('admin.blog.posts.create')"><x-ui::icon name="plus" /> {{ __('Nouvel article') }}</x-ui::button>
                    </x-ui::empty-state>
                @endif
            @else
                <x-ui::table>
                    <x-ui::table.head>
                        <x-admin.sortable-header column="title" :sorted="$sorted->value" :direction="$sortDirection" :first="true">{{ __('Article') }}</x-admin.sortable-header>
                        <x-admin.sortable-header column="status" :sorted="$sorted->value" :direction="$sortDirection">{{ __('Statut') }}</x-admin.sortable-header>
                        <x-admin.sortable-header column="category" :sorted="$sorted->value" :direction="$sortDirection" hidden="md">{{ __('Catégorie') }}</x-admin.sortable-header>
                        <x-ui::table.header-cell hidden="lg">{{ __('Langues') }}</x-ui::table.header-cell>
                        <x-admin.sortable-header column="date" :sorted="$sorted->value" :direction="$sortDirection" hidden="sm">{{ __('Date (UTC)') }}</x-admin.sortable-header>
                        <x-ui::table.header-cell hidden="lg">{{ __('Origine') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell :last="true"><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.header-cell>
                    </x-ui::table.head>
                    <x-ui::table.body>
                        @foreach ($posts as $post)
                            @php($editUrl = route('admin.blog.posts.edit', ['post' => $post->id]))
                            {{-- Toute la ligne ouvre l'article ; le titre reste un vrai lien pour le clavier. --}}
                            <x-ui::table.row wire:key="post-{{ $post->id }}"
                                x-on:click="if (! $event.target.closest('a, button')) window.location.href = '{{ $editUrl }}'">
                                <x-ui::table.cell :first="true" :nowrap="false" variant="primary">
                                    {{-- La vignette laisse sa place au titre sur telephone. --}}
                                    <a href="{{ $editUrl }}" class="flex items-center gap-3 sm:min-w-[14rem]">
                                        @if ($post->thumbnailUrl)
                                            <img src="{{ $post->thumbnailUrl }}" alt="" width="64" height="40" loading="lazy" class="hidden h-10 w-16 shrink-0 rounded-lg object-cover sm:block">
                                        @else
                                            <span class="hidden h-10 w-16 shrink-0 items-center justify-center rounded-lg bg-elevated text-muted sm:flex"><x-ui::icon name="photo" /></span>
                                        @endif
                                        <span @class(['font-medium', 'text-primary' => $post->title, 'italic text-muted' => ! $post->title])>{{ $post->title ?? __('Sans titre') }}</span>
                                    </a>
                                </x-ui::table.cell>
                                <x-ui::table.cell><x-admin.blog.status-badge :status="$post->status" /></x-ui::table.cell>
                                <x-ui::table.cell hidden="md">{{ $post->categoryName ?? '-' }}</x-ui::table.cell>
                                <x-ui::table.cell hidden="lg">
                                    <x-admin.blog.locale-states :written="$post->writtenLocales" />
                                </x-ui::table.cell>
                                <x-ui::table.cell hidden="sm" variant="muted">
                                    @if ($post->status === \App\Enums\Blog\BlogPostStatus::Scheduled && $post->scheduledFor)
                                        {{ __('Prévu le :date', ['date' => $post->scheduledFor->format('d/m/Y H:i')]) }}
                                    @elseif ($post->publishedAt)
                                        {{ $post->publishedAt->format('d/m/Y') }}
                                    @else
                                        -
                                    @endif
                                </x-ui::table.cell>
                                <x-ui::table.cell hidden="lg">{{ $post->origin->label() }}</x-ui::table.cell>
                                <x-ui::table.cell :last="true" align="right">
                                    <x-ui::row-menu.trigger menu="posts" :label="__('Actions pour :title', ['title' => $post->title ?? __('Sans titre')])"
                                        :row="[
                                            'id' => $post->id,
                                            'title' => $post->title ?? __('Sans titre'),
                                            'published' => $post->status === \App\Enums\Blog\BlogPostStatus::Published,
                                            'editUrl' => $editUrl,
                                            'previewUrl' => route('admin.blog.posts.preview', ['post' => $post->id, 'locale' => 'fr']),
                                            'publicUrl' => $post->publicUrl,
                                        ]" />
                                </x-ui::table.cell>
                            </x-ui::table.row>
                        @endforeach
                    </x-ui::table.body>
                </x-ui::table>
            @endif

            {{-- Toujours affiche, meme sur une seule page : le nombre d'articles et le choix par page. --}}
            @if ($posts !== null && $posts->total() > 0)
                <x-ui::pagination :paginator="$posts" mode="livewire" :per-page="$perPageShown" :per-page-choices="$perPageChoices" per-page-model="perPage" />
            @endif
        </x-ui::card>

        {{-- Un seul menu pour toutes les lignes : chaque ligne ne porte que son bouton. --}}
        <x-ui::row-menu name="posts">
            <x-ui::dropdown-item icon="pencil-square" href="#" x-bind:href="row.editUrl">{{ __('Modifier') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="eye" href="#" x-bind:href="row.previewUrl" target="_blank" rel="noopener">{{ __('Aperçu') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="arrow-top-right-on-square" href="#" x-show="row.publicUrl" x-bind:href="row.publicUrl" target="_blank" rel="noopener">{{ __('Voir la page publique') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item :separator="true" />
            <x-ui::dropdown-item icon="trash" :danger="true" x-on:click="pending = row; $dispatch('ui-open-modal', 'delete-post')">{{ __('Supprimer') }}</x-ui::dropdown-item>
        </x-ui::row-menu>

        <x-ui::modal name="delete-post" variant="confirm" :title="__('Supprimer cet article ?')">
            <p class="font-medium text-primary" x-text="pending.title"></p>
            <p x-show="pending.published">{{ __('Il est publié : ses adresses répondront ensuite que la page n\'existe plus.') }}</p>
            <p>{{ __('La suppression est définitive.') }}</p>

            <x-slot:actions>
                <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'delete-post')">{{ __('Annuler') }}</x-ui::button>
                <x-ui::button variant="danger" x-on:click="$wire.delete(pending.id); $dispatch('ui-close-modal', 'delete-post')">{{ __('Supprimer') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::modal>
    @enderror
</div>
