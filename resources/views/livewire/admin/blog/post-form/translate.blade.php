{{-- Traduction assistee d'une langue (tache 6.14), en tete de son onglet : un menu « Traduire depuis… » qui
     propose les autres langues deja redigees. Si la langue a deja du texte, une fenetre demande confirmation
     (translate-post, dans dialogs.blade.php). Pendant la traduction, le bouton le dit, sans rien deplacer.
     Un peu moins haut que les boutons du kit (36 px au lieu de 44), centre et nomme « Tout traduire depuis… »
     pour se distinguer des boutons de chaque champ (demandes de Micha du 24/11/2026).
     $locale, $locales (code => nom de la langue). --}}
@php
    $fromLabels = ['en' => __('Depuis l\'anglais'), 'fr' => __('Depuis le français'), 'es' => __('Depuis l\'espagnol')];
@endphp
<div class="flex justify-center" data-translate="{{ $locale }}">
    <template x-if="translation.pending === '{{ $locale }}' && translation.field === null">
        <x-ui::button variant="secondary" size="compact" class="min-h-9" disabled data-translation-pending>
            <x-ui::spinner size="sm" />
            {{ __('Traduction en cours…') }}
        </x-ui::button>
    </template>
    <template x-if="! (translation.pending === '{{ $locale }}' && translation.field === null)">
        <x-ui::dropdown width="w-56">
            <x-slot:trigger>
                <x-ui::button variant="secondary" size="compact" class="min-h-9" aria-haspopup="menu" x-bind:disabled="translation.pending !== null" data-translate-menu>
                    <x-ui::icon name="language" />
                    {{ __('Tout traduire depuis…') }}
                </x-ui::button>
            </x-slot:trigger>
            @foreach (array_keys($locales) as $from)
                @continue($from === $locale)
                <x-ui::dropdown-item x-show="state('{{ $from }}') !== 'empty'" x-on:click="askTranslation('{{ $from }}')" data-translate-from="{{ $from }}">{{ $fromLabels[$from] ?? $from }}</x-ui::dropdown-item>
            @endforeach
            <p x-show="{{ collect(array_keys($locales))->reject(fn ($from) => $from === $locale)->map(fn ($from) => "state('{$from}') === 'empty'")->implode(' && ') }}"
                class="px-3 py-2 text-[12px] text-muted">{{ __('Aucune autre langue n\'est encore rédigée.') }}</p>
        </x-ui::dropdown>
    </template>
</div>
