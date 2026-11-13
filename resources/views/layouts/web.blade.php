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
         Les pages publiques ont une adresse par langue (anglais sans prefixe, /fr, /es) : chaque version
         est son propre canonical et declare ses equivalents (hreflang, x-default = anglais). --}}
    <title>@yield('title', 'Festilaw · Your GPSR Responsible Person')</title>
    <meta name="description" content="@yield('meta_description', 'Sell safely in the European market. Festilaw is your GPSR Responsible Person, with dedicated support from entrepreneurs for entrepreneurs.')">
    <meta name="robots" content="@yield('robots', 'index, follow')">
    {{-- Une page paginee (blog) fournit son canonical : url()->current() ignore ?page=N. --}}
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <x-seo.alternates />

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', 'Festilaw · Your GPSR Responsible Person')">
    <meta property="og:description" content="@yield('meta_description', 'Sell safely in the European market. Festilaw is your GPSR Responsible Person, with dedicated support from entrepreneurs for entrepreneurs.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:image" content="@yield('og_image', asset('og-default.png'))">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="@yield('og_image_alt', 'Festilaw')">
    <meta property="og:locale" content="{{ config('festilaw.og_locales.'.app()->getLocale(), 'en_US') }}">

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

    {{-- Polices auto-hebergees (declarations dans resources/css/web/base/fonts.css). --}}
    <link rel="preload" href="{{ asset('fonts/inter-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/poiret-one-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/inter-latin-600-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- Livewire injecte ses assets uniquement lorsqu'un composant est rendu sur cette page. --}}

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

    @vite('resources/js/web.js')

    {{-- JS propre a la page. Les formulaires conservent l'injection automatique de Livewire. --}}
    @stack('scripts')
</body>
</html>
