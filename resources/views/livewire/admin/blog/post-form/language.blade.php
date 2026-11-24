{{-- Champs d'une langue du formulaire d'article. $locale, $language (nom de la langue en francais).
     Sous chaque champ traduisible, a droite, un petit bouton le traduit seul (demande de Micha du 24/11/2026) :
     sur la ligne de l'aide quand le champ en a une, que la vue ecrit alors elle-meme (le kit ne la loge pas a
     cote d'un bouton), et qu'elle cede au message d'erreur comme le ferait le kit.
     Les champs sont a 40 px les uns des autres, sauf le titre et son adresse, a 24 px, qui vont ensemble
     (Micha, 24/11/2026 : le formulaire etait trop serre, l'ecart du titre a l'adresse deja assez grand). --}}
@include('livewire.admin.blog.post-form.translate', ['locale' => $locale, 'locales' => $locales])

<div class="space-y-6">
    <div class="relative">
        <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.title" :recommended="60" />
        <x-ui::form-group :label="__('Titre')" for="title-{{ $locale }}" :data-missing="$view?->isMissing('title', $locale) ? 'title' : null" :error="$errors->first('texts.'.$locale.'.title')">
            <x-ui::input id="title-{{ $locale }}" wire:model="texts.{{ $locale }}.title" x-on:blur="suggestSlug('{{ $locale }}')" :error="$errors->has('texts.'.$locale.'.title')" />
        </x-ui::form-group>
        <div class="mt-1 flex justify-end">
            @include('livewire.admin.blog.post-form.translate-field', ['locale' => $locale, 'field' => 'title', 'locales' => $locales])
        </div>
    </div>

    <x-admin.blog.address-field :locale="$locale" :prefix="$addressPrefixes[$locale]" source="titre" :published="$isPublished" :missing="(bool) $view?->isMissing('slug', $locale)" />
</div>

<div class="relative">
    <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.excerpt" :recommended="160" />
    <x-ui::form-group :label="__('Extrait')" for="excerpt-{{ $locale }}" :error="$errors->first('texts.'.$locale.'.excerpt')">
        <x-ui::textarea id="excerpt-{{ $locale }}" rows="3" wire:model="texts.{{ $locale }}.excerpt" :error="$errors->has('texts.'.$locale.'.excerpt')" />
    </x-ui::form-group>
    <div class="mt-1 flex items-start justify-between gap-3">
        @unless ($errors->has('texts.'.$locale.'.excerpt'))
            <p class="pt-1 text-[12px] text-muted">{{ __('Deux phrases au plus. Affiché sur les cartes du blog et sous le titre de l\'article.') }}</p>
        @endunless
        <div class="ml-auto shrink-0">
            @include('livewire.admin.blog.post-form.translate-field', ['locale' => $locale, 'field' => 'excerpt', 'locales' => $locales])
        </div>
    </div>
</div>

@include('livewire.admin.blog.post-form.editor', ['locale' => $locale, 'language' => $language])

<x-admin.blog.google-fields :locale="$locale" :prefix="$addressPrefixes[$locale]" :locales="$locales"
    :title-fallback="__('le titre de l\'article')" :description-fallback="__('l\'extrait')" />
