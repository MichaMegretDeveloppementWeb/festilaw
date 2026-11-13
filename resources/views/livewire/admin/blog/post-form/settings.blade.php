{{-- Reglages communs aux trois langues : categorie, image principale, articles lies.
     UN seul bloc, deux presentations : une colonne qui reste en vue sur ordinateur, une feuille qui monte
     du bas de l'ecran sur telephone (ouverte par le bouton « Réglages » de la barre d'actions).
     A cette etape, les images et les articles lies sont affiches et conserves tels quels. --}}
<div x-show="settingsOpen" x-cloak x-transition.opacity x-on:click="settingsOpen = false" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden" aria-hidden="true"></div>

<aside id="post-settings" aria-labelledby="post-settings-title"
    x-bind:data-open="settingsOpen.toString()"
    x-bind:inert="! wide && ! settingsOpen"
    class="fixed inset-x-0 bottom-0 z-50 flex max-h-[85svh] translate-y-full flex-col rounded-t-2xl data-[open=true]:translate-y-0 bg-surface shadow-2xl transition-transform duration-200 ease-out lg:sticky lg:inset-x-auto lg:bottom-auto lg:top-6 lg:z-auto lg:max-h-none lg:translate-y-0 lg:rounded-xl lg:shadow-none lg:transition-none">
    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-subtle px-5 py-3.5">
        <div>
            <h2 id="post-settings-title" class="text-[13px] font-semibold text-primary">{{ __('Réglages de l\'article') }}</h2>
            <p class="mt-0.5 text-[12px] text-muted">{{ __('Communs aux trois langues') }}</p>
        </div>
        <button type="button" x-on:click="settingsOpen = false" aria-label="{{ __('Fermer les réglages') }}"
            class="-mr-2 flex h-11 w-11 items-center justify-center rounded-lg text-muted transition-colors hover:bg-elevated hover:text-secondary lg:hidden">
            <x-ui::icon name="x-mark" class="h-5 w-5" />
        </button>
    </div>

    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto p-5 lg:overflow-visible">
        {{-- Categorie --}}
        @if ($categoryOptions === [])
            <div>
                <p class="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Catégorie') }}</p>
                <p class="text-[13px] text-secondary">{{ __('Aucune catégorie n\'existe encore.') }}</p>
                <p class="mt-1 text-[12px] text-muted">
                    {{ __('Le brouillon peut être enregistré sans catégorie, mais il en faudra une pour publier.') }}
                    <a href="{{ route('admin.blog.categories.create') }}" class="font-medium text-secondary underline underline-offset-2 hover:text-primary">{{ __('Créer une catégorie') }}</a>
                </p>
            </div>
        @else
            <x-ui::form-group :label="__('Catégorie')" for="category-id" :error="$errors->first('categoryId')">
                <x-ui::combobox id="category-id" wire:model="categoryId" :options="$categoryOptions" :error="$errors->has('categoryId')"
                    :placeholder="__('Choisir une catégorie')" :search-placeholder="__('Rechercher une catégorie')" :empty="__('Aucune catégorie ne correspond')" />
            </x-ui::form-group>
        @endif

        {{-- Image principale --}}
        <div>
            <p class="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Image principale') }}</p>
            @php($featured = $featuredImageId !== null ? ($view?->images[$featuredImageId] ?? null) : null)
            @if ($featured && $featured['url'])
                <img src="{{ $featured['url'] }}" alt="{{ $featured['alt']['fr'] ?? '' }}" loading="lazy" class="aspect-video w-full rounded-lg object-cover">
            @else
                <div class="flex aspect-video w-full items-center justify-center rounded-lg border border-dashed border-default bg-elevated text-muted">
                    <x-ui::icon name="photo" class="h-8 w-8" stroke-width="1" />
                </div>
            @endif
            <p class="mt-1.5 text-[12px] text-muted">{{ __('L\'envoi et le remplacement des images arrivent à l\'étape suivante. L\'image actuelle est conservée.') }}</p>
        </div>

        {{-- Articles lies --}}
        <div>
            <p class="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Articles liés') }}</p>
            @if ($view === null || $view->related === [])
                <p class="text-[13px] text-secondary">{{ __('Aucun article choisi : le site propose les trois derniers de la même catégorie.') }}</p>
            @else
                <ol class="space-y-1.5">
                    @foreach ($view->related as $related)
                        <li class="flex items-center justify-between gap-3 rounded-lg bg-elevated px-3 py-2 text-[13px]">
                            <span @class(['truncate', 'text-primary' => $related['title'], 'italic text-muted' => ! $related['title']])>{{ $related['title'] ?? __('Sans titre') }}</span>
                            <x-admin.blog.status-badge :status="$related['status']" class="shrink-0" />
                        </li>
                    @endforeach
                </ol>
            @endif
            <p class="mt-1.5 text-[12px] text-muted">{{ __('Le choix des articles liés arrive à l\'étape suivante. La sélection actuelle est conservée.') }}</p>
        </div>
    </div>
</aside>
