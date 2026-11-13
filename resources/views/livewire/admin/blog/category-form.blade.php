{{-- Formulaire de categorie. L'onglet actif et l'adresse qui suit le nom vivent dans le navigateur
     (blogCategoryForm, resources/js/admin/blog-category-form.js). --}}
<div class="mx-auto max-w-3xl" x-data="blogCategoryForm()">
    <a href="{{ route('admin.blog.categories.index') }}" class="inline-flex items-center gap-1.5 text-[13px] text-muted transition-colors hover:text-primary">
        <x-ui::icon name="arrow-left" class="h-3.5 w-3.5" /> {{ __('Catégories') }}
    </a>

    <x-ui::page-header
        :title="$categoryId === null ? __('Nouvelle catégorie') : __('Modifier la catégorie')"
        :description="__('Le nom est obligatoire dans les trois langues : la catégorie a une page publique dans chacune.')"
        class="mb-6 mt-2" />

    <form wire:submit="save" class="space-y-6">
        @error('form')
            <x-ui::alert type="error" :title="__('La catégorie n\'a pas été enregistrée')">{{ $message }}</x-ui::alert>
        @enderror

        <x-ui::card :padding="false">
            <x-admin.blog.language-tabs :locales="$locales" :partial="false" />

            @foreach ($locales as $locale => $language)
                <div x-show="tab === '{{ $locale }}'" @if ($locale !== 'en') x-cloak @endif class="space-y-5 p-5" role="tabpanel">
                    <x-ui::form-group :label="__('Nom')" for="name-{{ $locale }}" :error="$errors->first('texts.'.$locale.'.name')">
                        <x-ui::input id="name-{{ $locale }}" wire:model="texts.{{ $locale }}.name" x-on:blur="suggestSlug('{{ $locale }}')" :error="$errors->has('texts.'.$locale.'.name')" />
                    </x-ui::form-group>

                    <x-admin.blog.address-field :locale="$locale" :prefix="$addressPrefixes[$locale]" source="nom" />

                    <x-ui::form-group :label="__('Description')" for="description-{{ $locale }}" :hint="__('Affichée en haut de la page de la catégorie.')" :error="$errors->first('texts.'.$locale.'.description')">
                        <x-ui::textarea id="description-{{ $locale }}" rows="3" wire:model="texts.{{ $locale }}.description" :error="$errors->has('texts.'.$locale.'.description')" />
                    </x-ui::form-group>

                    <x-admin.blog.google-fields :locale="$locale" :prefix="$addressPrefixes[$locale]"
                        :title-fallback="__('le nom de la catégorie')" :description-fallback="__('la description de la catégorie')" />
                </div>
            @endforeach
        </x-ui::card>

        <div class="flex items-center justify-end gap-2">
            <x-ui::button variant="ghost" :href="route('admin.blog.categories.index')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="submit" :loading="true" target="save">{{ __('Enregistrer la catégorie') }}</x-ui::button>
        </div>
    </form>

    @assets
        @vite('resources/js/admin/blog-category-form.js')
    @endassets
</div>
