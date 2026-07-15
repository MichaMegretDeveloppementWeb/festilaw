<section id="top" class="hero">
    <div class="hero__inner">
        <h1 class="hero__title">{{ __('Your GPSR') }} <span class="hero__title-em">{{ __('Responsible Person') }}</span></h1>
        <p class="hero__subtitle">{{ __('Sell safely in the European market.') }}</p>
        <p class="hero__lead">{{ __('Festilaw becomes your official EU Responsible Person, with real support from entrepreneurs, for entrepreneurs.') }}</p>
        <div class="hero__assurance">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7.5 12 12 15 14"/></svg>
            <span>{{ __('Your official EU Responsible Person address, ready within 24 hours.') }}</span>
        </div>
        <div class="hero__actions">
            <a href="{{ route('get-started.index') }}" class="btn btn--coral btn--lg">{{ __('Get compliant in 24h') }}</a>
            <a href="{{ route('home') }}#quiz" class="btn btn--outline-light btn--lg">{{ __('Am I eligible? Take the quiz') }}</a>
        </div>
    </div>
</section>
