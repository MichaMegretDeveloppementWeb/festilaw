{{-- Champs d'une langue du formulaire d'article. $locale, $language (nom de la langue en francais). --}}
<div class="relative">
    <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.title" :recommended="60" />
    <x-ui::form-group :label="__('Titre')" for="title-{{ $locale }}" :error="$errors->first('texts.'.$locale.'.title')">
        <x-ui::input id="title-{{ $locale }}" wire:model="texts.{{ $locale }}.title" x-on:blur="suggestSlug('{{ $locale }}')" :error="$errors->has('texts.'.$locale.'.title')" />
    </x-ui::form-group>
</div>

<x-admin.blog.address-field :locale="$locale" :prefix="$addressPrefixes[$locale]" source="titre" :published="$isPublished" />

<div class="relative">
    <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.excerpt" :recommended="160" />
    <x-ui::form-group :label="__('Extrait')" for="excerpt-{{ $locale }}" :hint="__('Deux phrases au plus. Affiché sur les cartes du blog et sous le titre de l\'article.')" :error="$errors->first('texts.'.$locale.'.excerpt')">
        <x-ui::textarea id="excerpt-{{ $locale }}" rows="3" wire:model="texts.{{ $locale }}.excerpt" :error="$errors->has('texts.'.$locale.'.excerpt')" />
    </x-ui::form-group>
</div>

<div>
    <p class="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Contenu') }}</p>
    <div class="rounded-lg border border-dashed border-default bg-elevated px-4 py-5 text-[13px] text-secondary">
        <p x-show="hasContent('{{ $locale }}')">{{ __('Le contenu en :language de cet article est conservé tel quel à l\'enregistrement.', ['language' => $language]) }}</p>
        <p x-show="! hasContent('{{ $locale }}')" x-cloak>{{ __('Aucun contenu en :language pour le moment.', ['language' => $language]) }}</p>
        <p class="mt-1 text-[12px] text-muted">{{ __('L\'éditeur de texte arrive à l\'étape suivante.') }}</p>
    </div>
</div>

<x-admin.blog.google-fields :locale="$locale" :prefix="$addressPrefixes[$locale]"
    :title-fallback="__('le titre de l\'article')" :description-fallback="__('l\'extrait')" />
