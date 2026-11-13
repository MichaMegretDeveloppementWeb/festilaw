<footer id="footer" class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__grid">
            <div class="site-footer__brandcol">
                <a href="{{ localized_route('home') }}" class="brand">Festilaw</a>
                <p class="site-footer__tagline">{{ __('Your GPSR Responsible Person in the EU. Compliance built by entrepreneurs, for entrepreneurs.') }}</p>
                <a href="mailto:team@festilaw.com" class="site-footer__email">team@festilaw.com</a>
            </div>

            <div class="site-footer__col">
                <div class="site-footer__heading">{{ __('Navigate') }}</div>
                <div class="site-footer__links">
                    <a href="{{ localized_route('home') }}">{{ __('Home') }}</a>
                    <a href="{{ localized_route('about') }}">{{ __('About') }}</a>
                    <a href="{{ localized_route('understand-gpsr') }}">{{ __('Understand GPSR') }}</a>
                    <a href="{{ localized_route('services') }}">{{ __('Our Services') }}</a>
                    <a href="{{ localized_route('pricing') }}">{{ __('Pricing') }}</a>
                    <a href="{{ localized_route('contact') }}">{{ __('Contact') }}</a>
                    <a href="{{ route('find-my-project') }}">{{ __('Access my project') }}</a>
                </div>
            </div>

            <div class="site-footer__col">
                <div class="site-footer__heading">{{ __('Legal') }}</div>
                <div class="site-footer__links">
                    <a href="{{ localized_route('legal-notice') }}">{{ __('Legal notice') }}</a>
                    <a href="{{ localized_route('privacy-policy') }}">{{ __('Privacy policy') }}</a>
                    <a href="{{ localized_route('terms') }}">{{ __('Terms') }}</a>
                    <a href="{{ localized_route('excluded-products') }}">{{ __('Excluded products') }}</a>
                </div>
            </div>

            <div class="site-footer__col">
                <div class="site-footer__heading">{{ __('Language') }}</div>
                <x-layout.web.lang-switch class="lang-switch--footer" />
                <p class="site-footer__note">{{ __('Consumer Law Ready certified · EU-funded training programme') }}</p>
            </div>
        </div>
        <div class="site-footer__copy">
            <span>&copy; {{ date('Y') }} Festilaw B.V. &middot; {{ __('Registered office: Nijmegen, the Netherlands') }} &middot; KvK 77058720 &middot; {{ __('VAT :number', ['number' => 'NL860886761B01']) }}</span>
        </div>
    </div>
</footer>
