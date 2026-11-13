{{-- Les pages publiques ont une adresse par langue : liens via localized_route(), page active via isCurrent()
     (request()->routeIs('pricing') ne reconnaitrait pas fr.pricing). --}}
@inject('localizedUrls', 'App\Services\Web\Localization\LocalizedUrlGenerator')
<header class="site-header">
    <nav class="site-header__menu site-header__menu--left">
        <a href="{{ localized_route('home') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('home')])>{{ __('Home') }}</a>
        <a href="{{ localized_route('about') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('about')])>{{ __('About') }}</a>
        <a href="{{ localized_route('understand-gpsr') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('understand-gpsr')])>{{ __('Understand GPSR') }}</a>
        <a href="{{ localized_route('services') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('services')])>{{ __('Our Services') }}</a>
    </nav>

    <a href="{{ localized_route('home') }}" class="brand-logo" aria-label="Festilaw">
        <picture>
            <source srcset="{{ asset('images/logo-festilaw-272.webp') }}" type="image/webp">
            <img src="{{ asset('images/logo-festilaw-272.png') }}" alt="Festilaw" width="104" height="104">
        </picture>
    </a>

    <div class="site-header__menu site-header__menu--right">
        <a href="{{ localized_route('pricing') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('pricing')])>{{ __('Pricing') }}</a>
        <a href="{{ localized_route('contact') }}" @class(['site-header__link', 'is-active' => $localizedUrls->isCurrent('contact')])>{{ __('Contact') }}</a>
        <x-layout.web.lang-switch />
        <a href="{{ route('get-started.index') }}" class="btn btn--coral btn--sm">{{ __('Get compliant in 24h') }}</a>
    </div>

    <button type="button" class="site-header__burger" aria-label="{{ __('Menu') }}" aria-expanded="false" aria-controls="site-mobile-menu">
        <span></span><span></span><span></span>
    </button>
    <div id="site-mobile-menu" class="site-header__mobile">
        <a href="{{ localized_route('home') }}">{{ __('Home') }}</a>
        <a href="{{ localized_route('about') }}">{{ __('About') }}</a>
        <a href="{{ localized_route('understand-gpsr') }}">{{ __('Understand GPSR') }}</a>
        <a href="{{ localized_route('services') }}">{{ __('Our Services') }}</a>
        <a href="{{ localized_route('pricing') }}">{{ __('Pricing') }}</a>
        <a href="{{ localized_route('contact') }}">{{ __('Contact') }}</a>
        <x-layout.web.lang-switch class="site-header__mobile-lang" />
        <a href="{{ route('get-started.index') }}" class="btn btn--coral">{{ __('Get compliant in 24h') }}</a>
    </div>
</header>
