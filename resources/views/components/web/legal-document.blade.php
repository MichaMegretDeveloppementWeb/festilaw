@props(['document'])
{{-- Corps d'un document legal dans la langue d'affichage : un gabarit par langue (comme les contrats), repli sur
     l'anglais s'il manque. Une version traduite rappelle en tete que la version anglaise fait foi et y renvoie
     (adresse anglaise de la meme page). --}}
@php
    $englishView = "web.legal.content.en.{$document}";
    $localeView = 'web.legal.content.'.app()->getLocale().".{$document}";
    $view = view()->exists($localeView) ? $localeView : $englishView;
@endphp

@if ($view !== $englishView)
    <p class="legal__translation-note">
        {{ __('This page is a translation provided for your convenience. In case of any discrepancy, the English version prevails.') }}
        <a data-track-label="Page légale, version anglaise" href="{{ localized_route($document, [], 'en') }}" hreflang="en">{{ __('Read the English version') }}</a>
    </p>
@endif

<div data-track-label="Page légale, lien">
    @include($view)
</div>
