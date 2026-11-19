@props(['locale', 'prefix', 'source', 'published' => false])
{{-- Adresse (slug) d'un article ou d'une categorie dans une langue. Elle suit le titre ou le nom tant
     qu'on ne l'a pas modifiee a la main.
     Sous le champ, une phrase fixe dit a quoi il sert, quel que soit son etat : une aide aiguille, elle ne
     decrit pas ce qui se passe (Micha, 19/11, la phrase qui changeait selon l'etat posait plus de questions
     qu'elle n'en resolvait). Le lien qui reprend l'adresse depuis le titre ou le nom ne parait que lorsqu'elle
     n'en vient plus. Pour une page en ligne, la phrase sur les anciens liens repond a la seule inquietude qu'un
     changement d'adresse fait naitre.
     $prefix : debut du chemin public ("/fr/blog/") ; $source : "titre" ou "nom" ;
     $published : la page est en ligne.
     Demande `resetSlug(langue)` dans la portee Alpine du formulaire (resources/js/admin/slug-field.js). --}}
<x-ui::form-group :label="__('Adresse de la page')" for="slug-{{ $locale }}" :error="$errors->first('texts.'.$locale.'.slug')">
    <x-ui::input id="slug-{{ $locale }}" :prefix="$prefix" wire:model="texts.{{ $locale }}.slug"
        x-on:input="$wire.slugEdited.{{ $locale }} = true" :error="$errors->has('texts.'.$locale.'.slug')"
        autocomplete="off" autocapitalize="none" spellcheck="false" />

    <p class="mt-1.5 text-[12px] text-muted" data-address-hint>
        {{ $source === 'nom' ? __('L\'adresse de la page de la catégorie, dans cette langue.') : __('L\'adresse de l\'article sur le site, dans cette langue.') }}
        <button type="button" x-show="$wire.slugEdited.{{ $locale }}" x-cloak x-on:click="resetSlug('{{ $locale }}')"
            class="font-medium text-secondary underline underline-offset-2 hover:text-primary">
            {{ $source === 'nom' ? __('Reprendre l\'adresse depuis le nom') : __('Reprendre l\'adresse depuis le titre') }}
        </button>
        @if ($published)
            <span class="mt-0.5 block" x-show="$wire.slugEdited.{{ $locale }}" x-cloak>{{ __('Après un changement d\'adresse, les anciens liens restent valables.') }}</span>
        @endif
    </p>
</x-ui::form-group>
