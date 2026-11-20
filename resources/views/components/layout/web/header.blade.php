{{-- Les pages publiques ont une adresse par langue : liens via localized_route(), page active via isCurrent()
     (request()->routeIs('pricing') ne reconnaitrait pas fr.pricing). --}}
@inject('localizedUrls', 'App\Services\Web\Localization\LocalizedUrlGenerator')
<header class="site-header">
    <nav class="site-header__menu site-header__menu--left">
        <a data-track-label="Menu, Accueil" href="{{ localized_route('home') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('home')])>{{ __('Home') }}</a>
        <a data-track-label="Menu, À propos" href="{{ localized_route('about') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('about')])>{{ __('About') }}</a>
        <a data-track-label="Menu, Comprendre le GPSR" href="{{ localized_route('understand-gpsr') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('understand-gpsr')])>{{ __('Understand GPSR') }}</a>
        <a data-track-label="Menu, Nos services" href="{{ localized_route('services') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('services')])>{{ __('Our Services') }}</a>
    </nav>

    <a data-track-label="Logo" href="{{ localized_route('home') }}" class="brand-logo" aria-label="Festilaw">
        <picture>
            <source srcset="{{ asset('images/logo-festilaw-272.webp') }}" type="image/webp">
            <img src="{{ asset('images/logo-festilaw-272.png') }}" alt="Festilaw" width="104" height="104">
        </picture>
    </a>

    <div class="site-header__menu site-header__menu--right">
        <a data-track-label="Menu, Tarifs" href="{{ localized_route('pricing') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('pricing')])>{{ __('Pricing') }}</a>
        @if ($showBlogLink ?? false)
            <a data-track-label="Menu, Blog" href="{{ localized_route('blog.index') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('blog.index', 'blog.category', 'blog.show')])>{{ __('Blog') }}</a>
        @endif
        <a data-track-label="Menu, Contact" href="{{ localized_route('contact') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('contact')])>{{ __('Contact') }}</a>
        <x-layout.web.lang-switch />
        <a data-track-event="header.to_offers" href="{{ route('get-started.index') }}" class="btn btn--coral btn--sm">{{ __('Get compliant in 24h') }}</a>
    </div>

    <button data-track-label="Menu mobile" type="button" class="site-header__burger" aria-label="{{ __('Menu') }}" aria-expanded="false" aria-controls="site-mobile-menu">
        <span></span><span></span><span></span>
    </button>
    <div id="site-mobile-menu" class="site-header__mobile">
        <a data-track-label="Menu, Accueil" href="{{ localized_route('home') }}">{{ __('Home') }}</a>
        <a data-track-label="Menu, À propos" href="{{ localized_route('about') }}">{{ __('About') }}</a>
        <a data-track-label="Menu, Comprendre le GPSR" href="{{ localized_route('understand-gpsr') }}">{{ __('Understand GPSR') }}</a>
        <a data-track-label="Menu, Nos services" href="{{ localized_route('services') }}">{{ __('Our Services') }}</a>
        <a data-track-label="Menu, Tarifs" href="{{ localized_route('pricing') }}">{{ __('Pricing') }}</a>
        @if ($showBlogLink ?? false)
            <a data-track-label="Menu, Blog" href="{{ localized_route('blog.index') }}">{{ __('Blog') }}</a>
        @endif
        <a data-track-label="Menu, Contact" href="{{ localized_route('contact') }}">{{ __('Contact') }}</a>
        <x-layout.web.lang-switch class="site-header__mobile-lang" />
        <a data-track-event="header.to_offers" href="{{ route('get-started.index') }}" class="btn btn--coral">{{ __('Get compliant in 24h') }}</a>
    </div>
</header>
