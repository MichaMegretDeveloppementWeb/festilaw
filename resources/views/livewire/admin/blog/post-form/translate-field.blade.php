{{-- Traduction d'un seul champ (demande de Micha du 24/11/2026) : un petit bouton sous le champ, a droite, qui
     ouvre le meme choix de langue que « Traduire depuis… » en tete de l'onglet. Il ne propose que les langues ou
     ce champ a du texte. Plus petit que les boutons du kit, a la demande de Micha, pour ne pas alourdir le
     formulaire. $locale, $field (title, excerpt, metaTitle, metaDescription, content), $locales. --}}
@php
    $fieldFromLabels = ['en' => __('Depuis l\'anglais'), 'fr' => __('Depuis le français'), 'es' => __('Depuis l\'espagnol')];
    $otherLocales = array_values(array_filter(array_keys($locales), static fn (string $from): bool => $from !== $locale));
@endphp
<div class="shrink-0" data-translate-field="{{ $field }}">
    <x-ui::dropdown width="w-56" align="right">
        <x-slot:trigger>
            <button type="button" aria-haspopup="menu" x-bind:disabled="translation.pending !== null"
                class="inline-flex items-center gap-1 whitespace-nowrap rounded-md px-1.5 py-1 text-[12px] font-medium text-secondary transition-colors hover:bg-elevated hover:text-primary disabled:cursor-not-allowed disabled:opacity-50">
                <template x-if="translation.pending === '{{ $locale }}' && translation.field === '{{ $field }}'">
                    <span class="inline-flex items-center gap-1"><x-ui::spinner size="sm" /> {{ __('Traduction en cours…') }}</span>
                </template>
                <template x-if="! (translation.pending === '{{ $locale }}' && translation.field === '{{ $field }}')">
                    <span class="inline-flex items-center gap-1"><x-ui::icon name="language" class="h-3.5 w-3.5" /> {{ __('Traduire depuis…') }}</span>
                </template>
            </button>
        </x-slot:trigger>
        @foreach ($otherLocales as $from)
            <x-ui::dropdown-item x-show="fieldHasText('{{ $from }}', '{{ $field }}')" x-on:click="askTranslation('{{ $from }}', '{{ $field }}')" data-translate-field-from="{{ $from }}">{{ $fieldFromLabels[$from] ?? $from }}</x-ui::dropdown-item>
        @endforeach
        <p x-show="{{ collect($otherLocales)->map(fn (string $from): string => "! fieldHasText('{$from}', '{$field}')")->implode(' && ') }}"
            class="px-3 py-2 text-[12px] text-muted">{{ __('Ce champ est vide dans les autres langues.') }}</p>
    </x-ui::dropdown>
</div>
