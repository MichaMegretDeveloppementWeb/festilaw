<header class="site-header">
    <a href="{{ route('home') }}" class="brand">
        <span class="brand__festi">festi</span><span class="brand__law">law</span><span class="brand__dot">.</span>
    </a>

    <nav class="site-header__nav">
        <a href="{{ route('home') }}" @class(['site-header__link', 'is-active' => request()->routeIs('home')])>Home</a>
        <a href="{{ route('home') }}#gpsr" class="site-header__link">Understand GPSR</a>
        <a href="{{ route('home') }}#services" class="site-header__link">Our Services</a>
        <a href="{{ route('home') }}#pricing" class="site-header__link">Pricing</a>
        <a href="{{ route('contact') }}" @class(['site-header__link', 'is-active' => request()->routeIs('contact')])>Contact</a>
    </nav>

    <div class="site-header__actions">
        <x-layout.web.lang-switch />
        <a href="{{ route('home') }}#quiz" class="btn btn--coral btn--sm">Get Compliant in 24h</a>
    </div>

    {{-- Menu mobile : bascule CSS pure (sera repris en Alpine plus tard) --}}
    <input type="checkbox" id="site-nav-toggle" class="site-header__toggle" hidden>
    <label for="site-nav-toggle" class="site-header__burger" aria-label="Menu">
        <span></span><span></span><span></span>
    </label>
    <div class="site-header__mobile">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('home') }}#gpsr">Understand GPSR</a>
        <a href="{{ route('home') }}#services">Our Services</a>
        <a href="{{ route('home') }}#pricing">Pricing</a>
        <a href="{{ route('contact') }}">Contact</a>
        <a href="{{ route('home') }}#quiz" class="btn btn--coral">Get Compliant in 24h</a>
    </div>
</header>
