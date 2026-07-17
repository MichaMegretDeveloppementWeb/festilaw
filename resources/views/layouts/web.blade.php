<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#696bf2">

    {{-- Favicons (generes depuis le logo). --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    {{-- SEO de base. Chaque page surcharge via @section ; sinon, repli calque sur la page d'accueil.
         Langue canonique du site : anglais. La traduction FR/ES est purement visuelle (locale en
         session) : un seul jeu d'URLs, un seul canonical, aucun hreflang. --}}
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
    <meta property="og:image" content="@yield('og_image', asset('og-default.png'))">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Festilaw">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Festilaw · Your GPSR Responsible Person')">
    <meta name="twitter:description" content="@yield('meta_description', 'Sell safely in the European market. Festilaw is your GPSR Responsible Person, with dedicated support from entrepreneurs for entrepreneurs.')">
    <meta name="twitter:image" content="@yield('og_image', asset('og-default.png'))">

    {{-- Metas additionnelles poussees par la page --}}
    @stack('meta')

    {{-- Donnees structurees : Organization + WebSite (global) + noeuds de la page + fil d'Ariane. --}}
    @php
        $seoNodes = $jsonLdNodes ?? [];
        if (! empty($breadcrumbs ?? [])) {
            $seoNodes[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn ($b, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $b['name'],
                    'item' => $b['url'],
                ], $breadcrumbs, array_keys($breadcrumbs)),
            ];
        }
    @endphp
    <x-seo.json-ld :nodes="$seoNodes" />

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
