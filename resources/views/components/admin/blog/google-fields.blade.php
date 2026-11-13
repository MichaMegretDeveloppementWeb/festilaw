@props(['locale', 'prefix', 'titleFallback', 'descriptionFallback'])
{{-- « Résultat dans Google » d'une langue : pourquoi ces deux champs existent, ce que Google affichera,
     puis le titre et la description, l'un sous l'autre, avec leur longueur conseillee.
     $prefix : debut du chemin public ; $titleFallback, $descriptionFallback : ce que Google affiche quand
     le champ reste vide (« le titre de l'article », « l'extrait »).
     Demande `googleTitle(langue)` et `googleDescription(langue)` dans la portee Alpine du formulaire. --}}
<div class="border-t border-subtle pt-5">
    <h3 class="text-[13px] font-semibold text-primary">{{ __('Résultat dans Google') }}</h3>
    <p class="mt-1 max-w-prose text-[12px] leading-relaxed text-muted">
        {{ __('Google affiche un titre et une description pour chaque page. Sans rien remplir ici, il reprend :title et :description. Remplissez ces champs seulement pour afficher autre chose dans Google.', ['title' => $titleFallback, 'description' => $descriptionFallback]) }}
    </p>

    {{-- Apercu, mis a jour pendant la saisie, avec les memes replis que la page publique. --}}
    <div class="mt-4 rounded-lg bg-elevated px-4 py-3" aria-hidden="true">
        <p class="truncate text-[12px] text-muted">{{ parse_url(url('/'), PHP_URL_HOST) }}<span x-text="@js(str_replace('/', ' › ', rtrim($prefix, '/'))) + ' › ' + (($wire.texts.{{ $locale }}.slug || '').trim() || '…')"></span></p>
        <p class="mt-0.5 truncate text-[15px] font-medium text-blue-700 dark:text-blue-400" x-text="googleTitle('{{ $locale }}') || @js(__('Titre de la page'))"></p>
        <p class="mt-0.5 line-clamp-2 text-[12px] leading-relaxed text-secondary" x-text="googleDescription('{{ $locale }}') || @js(__('Description de la page'))"></p>
    </div>

    <div class="mt-5 space-y-5">
        <div class="relative">
            <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.metaTitle" :recommended="60" />
            <x-ui::form-group :label="__('Titre pour Google')" for="meta-title-{{ $locale }}"
                :hint="__('Facultatif : :fallback sinon.', ['fallback' => $titleFallback])" :error="$errors->first('texts.'.$locale.'.metaTitle')">
                <x-ui::input id="meta-title-{{ $locale }}" wire:model="texts.{{ $locale }}.metaTitle" :error="$errors->has('texts.'.$locale.'.metaTitle')" />
            </x-ui::form-group>
        </div>

        <div class="relative">
            <x-admin.blog.char-count field="$wire.texts.{{ $locale }}.metaDescription" :recommended="160" />
            <x-ui::form-group :label="__('Description pour Google')" for="meta-description-{{ $locale }}"
                :hint="__('Facultatif : :fallback sinon.', ['fallback' => $descriptionFallback])" :error="$errors->first('texts.'.$locale.'.metaDescription')">
                <x-ui::textarea id="meta-description-{{ $locale }}" rows="3" wire:model="texts.{{ $locale }}.metaDescription" :error="$errors->has('texts.'.$locale.'.metaDescription')" />
            </x-ui::form-group>
        </div>
    </div>
</div>
