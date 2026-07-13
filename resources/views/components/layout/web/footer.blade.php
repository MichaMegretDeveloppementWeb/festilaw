<footer id="footer" class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__grid">
            <div class="site-footer__brandcol">
                <a href="{{ route('home') }}" class="brand">Festilaw</a>
                <p class="site-footer__tagline">Your GPSR Responsible Person in the EU. Compliance built by entrepreneurs, for entrepreneurs.</p>
                <a href="mailto:team@festilaw.com" class="site-footer__email">team@festilaw.com</a>
            </div>

            <div class="site-footer__col">
                <div class="site-footer__heading">Navigate</div>
                <div class="site-footer__links">
                    <a href="{{ route('home') }}">Home</a>
                    <a href="{{ route('about') }}">About</a>
                    <a href="{{ route('understand-gpsr') }}">Understand GPSR</a>
                    <a href="{{ route('services') }}">Our Services</a>
                    <a href="{{ route('pricing') }}">Pricing</a>
                    <a href="{{ route('contact') }}">Contact</a>
                </div>
            </div>

            <div class="site-footer__col">
                <div class="site-footer__heading">Legal</div>
                <div class="site-footer__links">
                    <a href="#footer">Legal notice</a>
                    <a href="#footer">Privacy policy</a>
                    <a href="#footer">Terms</a>
                    <a href="{{ route('excluded-products') }}">Excluded products</a>
                </div>
            </div>

            <div class="site-footer__col">
                <div class="site-footer__heading">Language</div>
                <x-layout.web.lang-switch class="lang-switch--footer" />
                <p class="site-footer__note">EU Consumer Law Ready, trained under the European Commission programme.</p>
            </div>
        </div>
        <div class="site-footer__copy">&copy; {{ date('Y') }} Festilaw. All rights reserved.</div>
    </div>
</footer>
