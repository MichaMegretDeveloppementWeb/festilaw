<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B1E45">

    {{-- SEO de base. Chaque page surcharge via @section ; sinon, repli calque sur la page d'accueil. --}}
    <title>@yield('title', 'Festilaw · Your GPSR Responsible Person')</title>
    <meta name="description" content="@yield('meta_description', 'Sell safely in the European market. Festilaw is your GPSR Responsible Person, with dedicated support from entrepreneurs for entrepreneurs.')">
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', 'Festilaw · Your GPSR Responsible Person')">
    <meta property="og:description" content="@yield('meta_description', 'Sell safely in the European market. Festilaw is your GPSR Responsible Person, with dedicated support from entrepreneurs for entrepreneurs.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.png'))">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Festilaw · Your GPSR Responsible Person')">
    <meta name="twitter:description" content="@yield('meta_description', 'Sell safely in the European market. Festilaw is your GPSR Responsible Person, with dedicated support from entrepreneurs for entrepreneurs.')">
    <meta name="twitter:image" content="@yield('og_image', asset('images/og-default.png'))">

    {{-- Metas additionnelles poussees par la page --}}
    @stack('meta')

    {{-- Donnees structurees (un seul contexte, plusieurs types, pretty JSON). Une page ajoute ses types via $jsonLdNodes. --}}
    <x-seo.json-ld :nodes="$jsonLdNodes ?? []" />

    {{-- Polices : Bunny Fonts (alternative GDPR-friendly a Google Fonts) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=poiret-one:400|inter:400,500,600,700|satisfy:400&display=swap">

    @livewireStyles

    {{-- CSS global de l'espace web (base + coquille) --}}
    @vite('resources/css/web.css')

    {{-- CSS propre a la page courante --}}
    @stack('styles')
</head>
<body>
    {{-- OUTIL PROVISOIRE : selecteur du bleu (choix cliente). A retirer avec le composant une fois valide. --}}
    <x-dev.blue-picker />

    <x-layout.web.header />

    <main>
        @yield('content')
    </main>

    <x-layout.web.footer />

    @vite('resources/js/app.js')

    {{-- JS propre a la page (ex. enregistrement de composants Alpine), AVANT le boot de Livewire/Alpine --}}
    @stack('scripts')

    {{-- Livewire (fournit Alpine, bundle) --}}
    @livewireScripts
</body>
</html>
