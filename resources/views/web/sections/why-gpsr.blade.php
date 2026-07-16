<section id="gpsr" class="risks">
    <div class="risks__inner">
        <div class="risks__head">
            <span class="eyebrow risks__eyebrow">{{ __('Why it matters') }}</span>
            <h2 class="risks__title">{{ __('The GPSR is now') }} <span class="risks__title-em">{{ __('mandatory') }}</span></h2>
            <p class="risks__intro">{{ __('Compliance is no longer optional. The EU General Product Safety Regulation (GPSR) is fully in effect. Any business selling consumer products to the EU must have an economic operator established in the EU who is responsible for those products. If you sell from outside the EU, that means appointing an EU Responsible Person and meeting strict traceability rules.') }}</p>
            @unless (request()->routeIs('understand-gpsr'))
                <p class="risks__more"><a href="{{ route('understand-gpsr') }}">{{ __('Understand the GPSR in plain language') }} →</a></p>
            @endunless
        </div>

        <div class="risks__grid">
            <div class="risks__item">
                <span class="risks__num">01</span>
                <h3 class="risks__item-title">{{ __('Customs seizures') }}</h3>
                <p class="risks__item-text">{{ __('Shipments lacking a valid Responsible Person can be detained, returned, or destroyed at the EU border.') }}</p>
            </div>
            <div class="risks__item">
                <span class="risks__num">02</span>
                <h3 class="risks__item-title">{{ __('Heavy fines') }}</h3>
                <p class="risks__item-text">{{ __('National authorities can impose significant penalties for selling without a compliant RP in place.') }}</p>
            </div>
            <div class="risks__item">
                <span class="risks__num">03</span>
                <h3 class="risks__item-title">{{ __('Marketplace removal') }}</h3>
                <p class="risks__item-text">{{ __('Platforms like Amazon, Etsy, and Shopify are actively delisting products and suspending accounts that fail to meet these standards.') }}</p>
            </div>
        </div>
    </div>
</section>
