@props(['locales', 'partial' => true])
{{-- Onglets de langue d'un formulaire du blog. $locales : code => nom de la langue en francais.
     Demande, dans la portee Alpine du formulaire, `tab` (onglet actif) et `state(langue)`, qui rend
     "written", "partial" ou "empty".
     $partial : l'etat « incomplete » existe pour ce formulaire (non pour une categorie, redigee des son nom).
     L'anneau dit l'etat de REDACTION de la langue, jamais celui de la publication : c'est ecrit dans la
     legende, dans l'info-bulle et pour les lecteurs d'ecran.
     Les onglets se touchent et s'espacent par leur marge interieure : aucune zone morte entre deux. --}}
<div class="flex items-center justify-between gap-4 border-b border-default pl-2 pr-5">
    <div class="-mb-px flex overflow-x-auto" role="tablist" aria-label="{{ __('Langue') }}">
        @foreach ($locales as $locale => $language)
            <button type="button" role="tab" x-on:click="tab = '{{ $locale }}'" x-bind:aria-selected="(tab === '{{ $locale }}').toString()"
                x-bind:class="tab === '{{ $locale }}' ? 'border-gray-900 text-gray-900 dark:border-white dark:text-white' : 'border-transparent text-muted hover:text-secondary'"
                x-bind:title="@js(ucfirst($language)) + ' : ' + { empty: @js(__('rien de rédigé')), partial: @js(__('incomplète')), written: @js(__('rédigée')) }[state('{{ $locale }}')]"
                class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-[13px] font-medium transition-colors">
                {{ ucfirst($language) }}
                <span aria-hidden="true" class="h-2 w-2 rounded-full border-[1.5px]"
                    x-bind:class="{ 'border-gray-300 dark:border-gray-600': state('{{ $locale }}') === 'empty', 'border-amber-500': state('{{ $locale }}') === 'partial', 'border-emerald-500': state('{{ $locale }}') === 'written' }"></span>
                <span class="sr-only" x-text="{ empty: @js(__('rien de rédigé')), partial: @js(__('incomplète')), written: @js(__('rédigée')) }[state('{{ $locale }}')]"></span>
                @if ($errors->has("texts.{$locale}.*"))
                    <span class="text-[11px] font-semibold text-red-700 dark:text-red-400">{{ __('à corriger') }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <p class="hidden shrink-0 items-center gap-x-3 text-[11px] text-muted md:flex" aria-hidden="true">
        <span class="inline-flex items-center gap-x-1"><span class="h-2 w-2 rounded-full border-[1.5px] border-emerald-500"></span>{{ __('rédigée') }}</span>
        @if ($partial)
            <span class="inline-flex items-center gap-x-1"><span class="h-2 w-2 rounded-full border-[1.5px] border-amber-500"></span>{{ __('incomplète') }}</span>
        @endif
        <span class="inline-flex items-center gap-x-1"><span class="h-2 w-2 rounded-full border-[1.5px] border-gray-300 dark:border-gray-600"></span>{{ __('vide') }}</span>
    </p>
</div>
