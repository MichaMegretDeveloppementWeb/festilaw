<footer id="footer" class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__grid">
            <div class="site-footer__brandcol">
                <a href="{{ route('home') }}" class="brand">Festilaw</a>
                <p class="site-footer__tagline">{{ __('Your GPSR Responsible Person in the EU. Compliance built by entrepreneurs, for entrepreneurs.') }}</p>
                <a href="mailto:team@festilaw.com" class="site-footer__email">team@festilaw.com</a>
            </div>

            <div class="site-footer__col">
                <div class="site-footer__heading">{{ __('Navigate') }}</div>
                <div class="site-footer__links">
                    <a href="{{ route('home') }}">{{ __('Home') }}</a>
                    <a href="{{ route('about') }}">{{ __('About') }}</a>
                    <a href="{{ route('understand-gpsr') }}">{{ __('Understand GPSR') }}</a>
                    <a href="{{ route('services') }}">{{ __('Our Services') }}</a>
                    <a href="{{ route('pricing') }}">{{ __('Pricing') }}</a>
                    <a href="{{ route('contact') }}">{{ __('Contact') }}</a>
                    <a href="{{ route('find-my-file') }}">{{ __('Access my file') }}</a>
                </div>
            </div>

            <div class="site-footer__col">
                <div class="site-footer__heading">{{ __('Legal') }}</div>
                <div class="site-footer__links">
                    <a href="#footer">{{ __('Legal notice') }}</a>
                    <a href="#footer">{{ __('Privacy policy') }}</a>
                    <a href="#footer">{{ __('Terms') }}</a>
                    <a href="{{ route('excluded-products') }}">{{ __('Excluded products') }}</a>
                </div>
            </div>

            <div class="site-footer__col">
                <div class="site-footer__heading">{{ __('Language') }}</div>
                <x-layout.web.lang-switch class="lang-switch--footer" />
                <p class="site-footer__note">{{ __('EU Consumer Law Ready, trained under the European Commission programme.') }}</p>
            </div>
        </div>
        <div class="site-footer__copy">&copy; {{ date('Y') }} Festilaw. {{ __('All rights reserved.') }}</div>
    </div>
</footer>
