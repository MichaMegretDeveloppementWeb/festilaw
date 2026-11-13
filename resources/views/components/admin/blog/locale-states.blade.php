@props(['written' => [], 'locales' => ['en', 'fr', 'es']])
{{-- Etat de redaction des langues d'un article, dans une liste : un anneau fin par langue.
     Vert = la langue est redigee (titre, adresse et contenu) ; gris = a rediger. Cela ne dit rien de la
     publication, qui a sa propre pastille de statut. $written : langues redigees. --}}
@php
    $names = ['en' => __('Anglais'), 'fr' => __('Français'), 'es' => __('Espagnol')];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-x-2.5']) }}>
    @foreach ($locales as $locale)
        @php
            $isWritten = in_array($locale, $written, true);
        @endphp
        <span class="inline-flex items-center gap-x-1 text-[11px] font-medium text-secondary"
            title="{{ ($names[$locale] ?? strtoupper($locale)).' : '.($isWritten ? __('rédigée') : __('à rédiger')) }}">
            <span aria-hidden="true" @class([
                'h-2 w-2 rounded-full border-[1.5px]',
                'border-emerald-500 dark:border-emerald-400' => $isWritten,
                'border-gray-300 dark:border-gray-600' => ! $isWritten,
            ])></span>
            {{ strtoupper($locale) }}
            <span class="sr-only">{{ $isWritten ? __('rédigée') : __('à rédiger') }}</span>
        </span>
    @endforeach
</span>
