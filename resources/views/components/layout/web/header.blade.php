<header class="site-header">
    <nav class="site-header__menu site-header__menu--left">
        <a href="{{ route('home') }}" @class(['site-header__link', 'is-active' => request()->routeIs('home')])>Home</a>
        <a href="{{ route('about') }}" @class(['site-header__link', 'is-active' => request()->routeIs('about')])>About</a>
        <a href="{{ route('understand-gpsr') }}" @class(['site-header__link', 'is-active' => request()->routeIs('understand-gpsr')])>Understand GPSR</a>
        <a href="{{ route('services') }}" @class(['site-header__link', 'is-active' => request()->routeIs('services')])>Our Services</a>
    </nav>

    <a href="{{ route('home') }}" class="brand-logo" aria-label="Festilaw">
        <img src="{{ asset('logo-festilaw.jpg') }}" alt="Festilaw" width="104" height="104">
    </a>

    <div class="site-header__menu site-header__menu--right">
        <a href="{{ route('pricing') }}" @class(['site-header__link', 'is-active' => request()->routeIs('pricing')])>Pricing</a>
        <a href="{{ route('contact') }}" @class(['site-header__link', 'is-active' => request()->routeIs('contact')])>Contact</a>
        <x-layout.web.lang-switch />
        <a href="{{ route('get-started.index') }}" class="btn btn--coral btn--sm">Get compliant in 24h</a>
    </div>

    {{-- Menu mobile : bascule CSS pure --}}
    <label for="site-nav-toggle" class="site-header__burger" aria-label="Menu">
        <span></span><span></span><span></span>
    </label>
    <input type="checkbox" id="site-nav-toggle" class="site-header__toggle" hidden>
    <div class="site-header__mobile">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('about') }}">About</a>
        <a href="{{ route('understand-gpsr') }}">Understand GPSR</a>
        <a href="{{ route('services') }}">Our Services</a>
        <a href="{{ route('pricing') }}">Pricing</a>
        <a href="{{ route('contact') }}">Contact</a>
        <a href="{{ route('get-started.index') }}" class="btn btn--coral">Get compliant in 24h</a>
    </div>
</header>
