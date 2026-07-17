<header class="site-header">
    <nav class="site-header__menu site-header__menu--left">
        <a href="{{ route('home') }}" @class(['site-header__link', 'is-active' => request()->routeIs('home')])>{{ __('Home') }}</a>
        <a href="{{ route('about') }}" @class(['site-header__link', 'is-active' => request()->routeIs('about')])>{{ __('About') }}</a>
        <a href="{{ route('understand-gpsr') }}" @class(['site-header__link', 'is-active' => request()->routeIs('understand-gpsr')])>{{ __('Understand GPSR') }}</a>
        <a href="{{ route('services') }}" @class(['site-header__link', 'is-active' => request()->routeIs('services')])>{{ __('Our Services') }}</a>
    </nav>

    <a href="{{ route('home') }}" class="brand-logo" aria-label="Festilaw">
        <img src="{{ asset('logo-festilaw.jpg') }}" alt="Festilaw" width="104" height="104">
    </a>

    <div class="site-header__menu site-header__menu--right">
        <a href="{{ route('pricing') }}" @class(['site-header__link', 'is-active' => request()->routeIs('pricing')])>{{ __('Pricing') }}</a>
        <a href="{{ route('contact') }}" @class(['site-header__link', 'is-active' => request()->routeIs('contact')])>{{ __('Contact') }}</a>
        <x-layout.web.lang-switch />
        <a href="{{ route('get-started.index') }}" class="btn btn--coral btn--sm">{{ __('Get compliant in 24h') }}</a>
    </div>

    {{-- Menu mobile : bascule en vanilla JS (resources/js/web/layout/header.js) --}}
    <button type="button" class="site-header__burger" aria-label="{{ __('Menu') }}" aria-expanded="false" aria-controls="site-mobile-menu">
        <span></span><span></span><span></span>
    </button>
    <div id="site-mobile-menu" class="site-header__mobile">
        <a href="{{ route('home') }}">{{ __('Home') }}</a>
        <a href="{{ route('about') }}">{{ __('About') }}</a>
        <a href="{{ route('understand-gpsr') }}">{{ __('Understand GPSR') }}</a>
        <a href="{{ route('services') }}">{{ __('Our Services') }}</a>
        <a href="{{ route('pricing') }}">{{ __('Pricing') }}</a>
        <a href="{{ route('contact') }}">{{ __('Contact') }}</a>
        <x-layout.web.lang-switch class="site-header__mobile-lang" />
        <a href="{{ route('get-started.index') }}" class="btn btn--coral">{{ __('Get compliant in 24h') }}</a>
    </div>
</header>
