{{-- Equivalents linguistiques d'une page publique (une adresse par langue) : balises hreflang, x-default
     vers la langue par defaut, et og:locale:alternate. Rien sur une page sans adresse par langue (tunnel,
     espace client, erreurs). Cf. LocalizedUrlGenerator::alternates(). --}}
@inject('localizedUrls', 'App\Services\Web\Localization\LocalizedUrlGenerator')
@php
    $alternates = $localizedUrls->alternates();
    $ogLocales = config('festilaw.og_locales');
@endphp
@foreach ($alternates as $locale => $url)
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">
@endforeach
@if ($alternates !== [])
    <link rel="alternate" hreflang="x-default" href="{{ $alternates[$localizedUrls->defaultLocale()] }}">
@endif
@foreach ($alternates as $locale => $url)
    @if ($locale !== app()->getLocale() && isset($ogLocales[$locale]))
        <meta property="og:locale:alternate" content="{{ $ogLocales[$locale] }}">
    @endif
@endforeach
