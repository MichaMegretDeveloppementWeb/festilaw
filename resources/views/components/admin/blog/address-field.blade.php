@props(['locale', 'prefix', 'source', 'published' => false])
{{-- Adresse (slug) d'un article ou d'une categorie dans une langue. Elle suit le titre ou le nom tant
     qu'on ne l'a pas modifiee a la main ; le texte sous le champ dit dans quel cas on est.
     $prefix : debut du chemin public ("/fr/blog/") ; $source : "titre" ou "nom" ;
     $published : la page est en ligne (changer l'adresse a alors une consequence a dire).
     Demande `resetSlug(langue)` dans la portee Alpine du formulaire (resources/js/admin/slug-field.js). --}}
<x-ui::form-group :label="__('Adresse de la page')" for="slug-{{ $locale }}" :error="$errors->first('texts.'.$locale.'.slug')">
    <x-ui::input id="slug-{{ $locale }}" :prefix="$prefix" wire:model="texts.{{ $locale }}.slug"
        x-on:input="$wire.slugEdited.{{ $locale }} = true" :error="$errors->has('texts.'.$locale.'.slug')"
        autocomplete="off" autocapitalize="none" spellcheck="false" />

    <p class="mt-1.5 text-[12px] text-muted" x-show="! $wire.slugEdited.{{ $locale }}">
        {{ $source === 'nom' ? __('Créée à partir du nom. Vous pouvez la modifier.') : __('Créée à partir du titre. Vous pouvez la modifier.') }}
    </p>
    <p class="mt-1.5 text-[12px] text-muted" x-show="$wire.slugEdited.{{ $locale }}" x-cloak>
        {{ $source === 'nom' ? __('Adresse personnalisée. Elle ne change plus quand le nom change.') : __('Adresse personnalisée. Elle ne change plus quand le titre change.') }}
        <button type="button" class="font-medium text-secondary underline underline-offset-2 hover:text-primary" x-on:click="resetSlug('{{ $locale }}')">
            {{ $source === 'nom' ? __('Reprendre l\'adresse du nom') : __('Reprendre l\'adresse du titre') }}
        </button>
        @if ($published)
            <span class="mt-0.5 block">{{ __('Après un changement d\'adresse, les anciens liens restent valables.') }}</span>
        @endif
    </p>
</x-ui::form-group>
