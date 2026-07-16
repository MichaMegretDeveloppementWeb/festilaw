<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>@yield('title', 'Back-office · Festilaw')</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    @livewireStyles
    @vite('resources/css/admin.css')
    @stack('styles')
</head>
<body class="admin">
    @auth
        <header class="admin-nav">
            <div class="admin-nav__inner">
                <a href="{{ route('admin.submissions.index') }}" class="admin-nav__brand">Festilaw · {{ __('Back-office') }}</a>
                <nav class="admin-nav__links">
                    <a href="{{ route('admin.submissions.index') }}" @class(['is-active' => request()->routeIs('admin.submissions.*')])>{{ __('Dossiers') }}</a>
                </nav>
                <form method="POST" action="{{ route('admin.logout') }}" class="admin-nav__logout">
                    @csrf
                    <span class="admin-nav__user">{{ auth()->user()->email }}</span>
                    <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">{{ __('Se déconnecter') }}</button>
                </form>
            </div>
        </header>
    @endauth

    <main class="admin-main">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @vite('resources/js/app.js')
    @livewireScripts
</body>
</html>
