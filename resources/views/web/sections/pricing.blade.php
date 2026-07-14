<section id="pricing" class="pricing">
    <div class="pricing__inner">
        <div class="pricing__head">
            <span class="eyebrow">Pricing</span>
            <h2 class="pricing__title">Simple, Fair, Transparent</h2>
        </div>
        <div class="pricing__grid">
            <div class="pricing__card">
                <h3 class="pricing__name">Creator Pack</h3>
                <p class="pricing__desc">Small creators, up to 9 products.</p>
                <div class="pricing__amount">&euro;333</div>
                <div class="pricing__period">per year</div>
                <a href="{{ route('get-started.starter') }}" class="btn btn--outline-dark pricing__cta">Choose Creator</a>
            </div>
            <div class="pricing__card pricing__card--featured">
                <span class="pricing__badge">Most popular</span>
                <h3 class="pricing__name">Pro Pack</h3>
                <p class="pricing__desc">Growing brands, 10 to 100 products.</p>
                <div class="pricing__amount">&euro;1,200</div>
                <div class="pricing__period">per year</div>
                <a href="{{ route('get-started.pro') }}" class="btn btn--coral pricing__cta">Choose Pro</a>
            </div>
            <div class="pricing__card">
                <h3 class="pricing__name">Scale Pack</h3>
                <p class="pricing__desc">100+ products, with full audit.</p>
                <div class="pricing__amount">Custom</div>
                <div class="pricing__period">tailored quote</div>
                <a href="{{ route('contact') }}" class="btn btn--outline-dark pricing__cta">Talk to us</a>
            </div>
        </div>
    </div>
</section>
