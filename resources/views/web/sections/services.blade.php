<section id="services" class="services">
    <div class="services__inner">
        <div class="services__head">
            <span class="eyebrow">{{ __('Our services') }}</span>
            <h2 class="services__title">{{ __('From legal representation to regulatory watch,') }} <span class="services__title-em">{{ __('we handle it all') }}</span></h2>
        </div>

        <div class="services__grid">
            <article class="services__card">
                <span class="services__num">01</span>
                <h3 class="services__card-title">{{ __('Legal Representation') }}</h3>
                <p class="services__card-text">{{ __('We don\'t just host your name; we become your official legal liaison in Europe. We handle interactions with authorities directly to assist you and guide you through EU bureaucracy in case of need.') }}</p>
            </article>
            <article class="services__card">
                <span class="services__num">02</span>
                <h3 class="services__card-title">{{ __('Compliance Assessment') }}</h3>
                <p class="services__card-text">{{ __('No more guesswork. We thoroughly assess your products to pinpoint the exact European directives that apply to your business, giving you a clear and compliant roadmap.') }}</p>
            </article>
            <article class="services__card">
                <span class="services__num">03</span>
                <h3 class="services__card-title">{{ __('Documentation & Labeling Strategy') }}</h3>
                <p class="services__card-text">{{ __('We advise you on exactly which documents and certificates are mandatory, while defining the precise markings, languages, and tracking data required on your packaging for full compliance.') }}</p>
            </article>
            <article class="services__card">
                <span class="services__num">04</span>
                <h3 class="services__card-title">{{ __('Authority Liaison') }}</h3>
                <p class="services__card-text">{{ __('Whenever a customs officer or market authority contacts you or requests information, we step in as your official intermediary to manage the communication and ensure you are best prepared to achieve compliance.') }}</p>
            </article>
            <article class="services__card">
                <span class="services__num">05</span>
                <h3 class="services__card-title">{{ __('Safety Gate Monitoring') }}</h3>
                <p class="services__card-text">{{ __('We regularly scan the EU Safety Gate portal to ensure your products have not been flagged or reported, giving you an early warning system to manage risks immediately.') }}</p>
            </article>
            <article class="services__card">
                <span class="services__num">06</span>
                <h3 class="services__card-title">{{ __('Regulatory Watch') }}</h3>
                <p class="services__card-text">{{ __('We run dedicated compliance health checks several times a year to analyze if upcoming European regulations could impact your business, keeping you one step ahead.') }}</p>
            </article>
        </div>

        <div class="services__more">
            <a href="{{ route('contact') }}" class="services__more-link">{{ __('Not sure what you need? Talk to us') }}
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</section>
