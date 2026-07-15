<section id="pricing" class="pricing">
    <div class="pricing__inner">
        <div class="pricing__head">
            <span class="eyebrow">{{ __('Pricing') }}</span>
            <h2 class="pricing__title">{{ __('Simple, Fair, Transparent') }}</h2>
        </div>
        <div class="pricing__grid">
            <div class="pricing__card">
                <h3 class="pricing__name">{{ __('Creator Pack') }}</h3>
                <p class="pricing__desc">{{ __('Small creators, up to 9 products.') }}</p>
                <div class="pricing__amount">&euro;333</div>
                <div class="pricing__period">{{ __('per year') }}</div>
                <a href="{{ route('get-started.starter') }}" class="btn btn--outline-dark pricing__cta">{{ __('Choose Creator') }}</a>
            </div>
            <div class="pricing__card pricing__card--featured">
                <span class="pricing__badge">{{ __('Most popular') }}</span>
                <h3 class="pricing__name">{{ __('Pro Pack') }}</h3>
                <p class="pricing__desc">{{ __('Growing brands, 10 to 100 products.') }}</p>
                <div class="pricing__amount">&euro;1,200</div>
                <div class="pricing__period">{{ __('per year') }}</div>
                <a href="{{ route('get-started.pro') }}" class="btn btn--coral pricing__cta">{{ __('Choose Pro') }}</a>
            </div>
            <div class="pricing__card">
                <h3 class="pricing__name">{{ __('Scale Pack') }}</h3>
                <p class="pricing__desc">{{ __('100+ products, with full audit.') }}</p>
                <div class="pricing__amount">{{ __('Custom') }}</div>
                <div class="pricing__period">{{ __('tailored quote') }}</div>
                <a href="{{ route('contact') }}" class="btn btn--outline-dark pricing__cta">{{ __('Talk to us') }}</a>
            </div>
        </div>
    </div>
</section>
