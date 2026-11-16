{{-- Reglages communs aux trois langues : categorie, image principale, image de partage, articles lies.
     UN seul bloc, deux presentations : une colonne qui reste en vue sur ordinateur, une feuille qui monte
     du bas de l'ecran sur telephone (ouverte par le bouton « Réglages » de la barre d'actions). --}}
<div x-show="settingsOpen" x-cloak x-transition.opacity x-on:click="settingsOpen = false" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden" aria-hidden="true"></div>

<aside id="post-settings" aria-labelledby="post-settings-title"
    x-bind:data-open="settingsOpen.toString()"
    x-bind:inert="! wide && ! settingsOpen"
    class="fixed inset-x-0 bottom-0 z-50 flex max-h-[85svh] translate-y-full flex-col rounded-t-2xl data-[open=true]:translate-y-0 bg-surface shadow-2xl transition-transform duration-200 ease-out lg:sticky lg:inset-x-auto lg:bottom-auto lg:top-6 lg:z-auto lg:max-h-[calc(100svh-7rem)] lg:translate-y-0 lg:rounded-xl lg:shadow-none lg:transition-none">
    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-subtle px-5 py-3.5">
        <div>
            <h2 id="post-settings-title" class="text-[15px] font-semibold leading-snug text-primary">{{ __('Réglages de l\'article') }}</h2>
            <p class="mt-0.5 text-[13px] text-muted">{{ __('Communs aux trois langues') }}</p>
        </div>
        <button type="button" x-on:click="settingsOpen = false" aria-label="{{ __('Fermer les réglages') }}"
            class="-mr-2 flex h-11 w-11 items-center justify-center rounded-lg text-muted transition-colors hover:bg-elevated hover:text-secondary lg:hidden">
            <x-ui::icon name="x-mark" class="h-5 w-5" />
        </button>
    </div>

    {{-- Le contenu defile a l'interieur du panneau : sur ordinateur, il ne depasse jamais la hauteur de l'ecran. --}}
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto p-5">
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

        {{-- Les limites affichees sont celles que le serveur applique (config/blog.php, section images). --}}
        @php
            $imageLimits = [
                'width' => config('blog.images.min_featured.width'),
                'height' => config('blog.images.min_featured.height'),
                'megabytes' => (int) floor(config('blog.images.max_upload_kilobytes') / 1024),
            ];
        @endphp

        @include('livewire.admin.blog.post-form.image-slot', [
            'usage' => 'featured',
            'property' => 'featuredImageId',
            'label' => __('Image principale'),
            'help' => __('En haut de l’article et sur sa carte. JPEG, PNG ou WebP, au moins :width × :height pixels, :megabytes Mo au plus.', $imageLimits),
        ])

        @include('livewire.admin.blog.post-form.image-slot', [
            'usage' => 'share',
            'property' => 'shareImageId',
            'label' => __('Image de partage (facultatif)'),
            'compact' => true,
            'help' => __('Affichée quand l’article est partagé sur un réseau social. Mêmes dimensions minimales que l’image principale. Si non renseignée, l’image principale est utilisée.'),
        ])

        @include('livewire.admin.blog.post-form.related')
    </div>
</aside>
