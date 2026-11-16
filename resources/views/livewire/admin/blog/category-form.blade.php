{{-- Formulaire de categorie, sur page blanche et sur toute la largeur. L'onglet actif et l'adresse qui suit le
     nom vivent dans le navigateur (blogCategoryForm, resources/js/admin/blog-category-form.js).
     Sur un grand ecran, chaque langue tient en deux colonnes : le nom, l'adresse et la description a gauche,
     « Résultat dans Google » a droite. Une seule colonne en dessous. L'enregistrement passe par la barre
     d'actions collee en bas, comme le formulaire d'article et les reglages. --}}
<div class="flex min-h-[calc(100svh-6.5rem)] flex-col sm:min-h-[calc(100svh-7.5rem)]" x-data="blogCategoryForm()">
    <a href="{{ route('admin.blog.categories.index') }}" class="inline-flex items-center gap-1.5 self-start text-[13px] text-muted transition-colors hover:text-primary">
        <x-ui::icon name="arrow-left" class="h-3.5 w-3.5" /> {{ __('Catégories') }}
    </a>

    <x-ui::page-header
        :title="$categoryId === null ? __('Nouvelle catégorie') : __('Modifier la catégorie')"
        :description="__('Le nom est obligatoire dans les trois langues : la catégorie a une page publique dans chacune.')"
        class="mb-8 mt-2" />

    <form wire:submit="save" id="category-form" class="pb-10">
        @error('form')
            <x-ui::alert type="error" class="mb-6" :title="__('La catégorie n\'a pas été enregistrée')">{{ $message }}</x-ui::alert>
        @enderror

        {{-- Les onglets debordent de la colonne comme les filets de la page : leur premier libelle reste aligne
             sur le titre. --}}
        <div class="-mx-4 sm:-mx-5">
            <x-admin.blog.language-tabs :locales="$locales" :partial="false" />
        </div>

        @foreach ($locales as $locale => $language)
            <div x-show="tab === '{{ $locale }}'" @if ($locale !== 'en') x-cloak @endif role="tabpanel"
                class="grid items-start gap-x-16 gap-y-8 pt-8 lg:grid-cols-2">
                <div class="min-w-0 space-y-6">
                    {{-- Le nom et la description servent a Google quand ses deux champs restent vides : memes
                         longueurs conseillees. --}}
                    <div class="relative">
                        <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.name" :recommended="60" />
                        <x-ui::form-group :label="__('Nom')" for="name-{{ $locale }}" :error="$errors->first('texts.'.$locale.'.name')">
                            <x-ui::input id="name-{{ $locale }}" wire:model="texts.{{ $locale }}.name" x-on:blur="suggestSlug('{{ $locale }}')" :error="$errors->has('texts.'.$locale.'.name')" />
                        </x-ui::form-group>
                    </div>

                    <x-admin.blog.address-field :locale="$locale" :prefix="$addressPrefixes[$locale]" source="nom" />

                    <div class="relative">
                        <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.description" :recommended="160" />
                        <x-ui::form-group :label="__('Description')" for="description-{{ $locale }}" :hint="__('Affichée en haut de la page de la catégorie.')" :error="$errors->first('texts.'.$locale.'.description')">
                            <x-ui::textarea id="description-{{ $locale }}" rows="4" wire:model="texts.{{ $locale }}.description" :error="$errors->has('texts.'.$locale.'.description')" />
                        </x-ui::form-group>
                    </div>
                </div>

                <x-admin.blog.google-fields class="min-w-0 lg:border-t-0 lg:pt-0" :locale="$locale" :prefix="$addressPrefixes[$locale]"
                    :title-fallback="__('le nom de la catégorie')" :description-fallback="__('la description de la catégorie')" />
            </div>
        @endforeach
    </form>

    <x-admin.action-bar>
        <div class="flex items-center justify-end gap-2">
            <x-ui::button variant="ghost" :href="route('admin.blog.categories.index')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="submit" form="category-form" :loading="true" target="save">{{ __('Enregistrer la catégorie') }}</x-ui::button>
        </div>
    </x-admin.action-bar>

    @assets
        @vite('resources/js/admin/blog-category-form.js')
    @endassets
</div>
