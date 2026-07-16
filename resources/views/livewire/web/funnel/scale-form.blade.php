<div>
    @if ($sent)
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="funnel-success__title">{{ __('Audit requested.') }}</h3>
            <p class="funnel-success__text">{{ __('We\'ve received your request and will be in touch shortly with the next steps and the €75 audit payment (deducted from your final contract).') }}</p>
            <a href="{{ route('home') }}" class="btn btn--outline-dark btn--sm">{{ __('Back to home') }}</a>
        </div>
    @else
        <form wire:submit="submit" class="funnel-form" novalidate>
            @error('form') <div class="funnel-form__error">{{ $message }}</div> @enderror

            <div class="hp-field" aria-hidden="true">
                <label for="scf-hp">{{ __('Leave this field empty') }}</label>
                <input type="text" id="scf-hp" wire:model="hp" tabindex="-1" autocomplete="off">
            </div>

            <div class="funnel-form__row funnel-form__row--two">
                <div class="funnel-form__field">
                    <label for="scf-company">{{ __('Company') }}</label>
                    <input type="text" id="scf-company" wire:model="company_name" placeholder="{{ __('Your company') }}">
                    @error('company_name') <span class="funnel-form__error">{{ $message }}</span> @enderror
                </div>
                <div class="funnel-form__field">
                    <label for="scf-name">{{ __('Your name') }} <span class="funnel-form__optional">{{ __('(optional)') }}</span></label>
                    <input type="text" id="scf-name" wire:model="first_name" autocomplete="given-name" placeholder="{{ __('First name') }}">
                </div>
            </div>

            <div class="funnel-form__field">
                <label for="scf-email">{{ __('Email') }}</label>
                <input type="email" id="scf-email" wire:model="email" autocomplete="email" placeholder="you@company.com">
                @error('email') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="funnel-form__field">
                <label for="scf-url">{{ __('Store or website') }} <span class="funnel-form__optional">{{ __('(optional)') }}</span></label>
                <input type="url" id="scf-url" wire:model="website_url" placeholder="https://your-shop.com">
                @error('website_url') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="funnel-form__field">
                <label for="scf-products">{{ __('What do you sell?') }} <span class="funnel-form__optional">{{ __('(optional)') }}</span></label>
                <input type="text" id="scf-products" wire:model="product_types" placeholder="{{ __('e.g. electronics, toys') }}">
            </div>

            <button type="submit" class="btn btn--coral" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">{{ __('Request my audit') }}</span>
                <span wire:loading wire:target="submit">{{ __('Sending') }}&hellip;</span>
            </button>

            <x-web.privacy-consent />
        </form>
    @endif
</div>
