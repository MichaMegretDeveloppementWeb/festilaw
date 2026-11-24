{{-- Champs d'une langue du formulaire d'article. $locale, $language (nom de la langue en francais). --}}
@include('livewire.admin.blog.post-form.translate', ['locale' => $locale, 'locales' => $locales])

<div class="relative">
    <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.title" :recommended="60" />
    <x-ui::form-group :label="__('Titre')" for="title-{{ $locale }}" :data-missing="$view?->isMissing('title', $locale) ? 'title' : null" :error="$errors->first('texts.'.$locale.'.title')">
        <x-ui::input id="title-{{ $locale }}" wire:model="texts.{{ $locale }}.title" x-on:blur="suggestSlug('{{ $locale }}')" :error="$errors->has('texts.'.$locale.'.title')" />
    </x-ui::form-group>
</div>

<x-admin.blog.address-field :locale="$locale" :prefix="$addressPrefixes[$locale]" source="titre" :published="$isPublished" :missing="(bool) $view?->isMissing('slug', $locale)" />

<div class="relative">
    <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.excerpt" :recommended="160" />
    <x-ui::form-group :label="__('Extrait')" for="excerpt-{{ $locale }}" :hint="__('Deux phrases au plus. Affiché sur les cartes du blog et sous le titre de l\'article.')" :error="$errors->first('texts.'.$locale.'.excerpt')">
        <x-ui::textarea id="excerpt-{{ $locale }}" rows="3" wire:model="texts.{{ $locale }}.excerpt" :error="$errors->has('texts.'.$locale.'.excerpt')" />
    </x-ui::form-group>
</div>

@include('livewire.admin.blog.post-form.editor', ['locale' => $locale, 'language' => $language])

<x-admin.blog.google-fields :locale="$locale" :prefix="$addressPrefixes[$locale]"
    :title-fallback="__('le titre de l\'article')" :description-fallback="__('l\'extrait')" />
