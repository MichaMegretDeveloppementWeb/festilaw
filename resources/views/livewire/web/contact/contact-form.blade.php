<div class="contact-form">
    @if ($sent)
        <div class="contact-form__success">
            <div class="contact-form__success-icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="contact-form__success-title">{{ __('Thanks, we got your message.') }}</h3>
            <p class="contact-form__success-text">{{ __('Our team will get back to you shortly.') }}</p>
            <button type="button" wire:click="$set('sent', false)" class="btn btn--outline-dark btn--sm">{{ __('Send another') }}</button>
        </div>
    @else
        <form wire:submit="save" class="contact-form__form" novalidate>
            @error('form') <div class="contact-form__error">{{ $message }}</div> @enderror

            <div class="hp-field" aria-hidden="true">
                <label for="cf-hp">{{ __('Leave this field empty') }}</label>
                <input type="text" id="cf-hp" wire:model="hp" tabindex="-1" autocomplete="off">
            </div>

            <div class="contact-form__field">
                <label for="cf-name">{{ __('Name') }}</label>
                <input type="text" id="cf-name" wire:model="name" autocomplete="name" placeholder="{{ __('Your name') }}">
                @error('name') <span class="contact-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="contact-form__field">
                <label for="cf-email">{{ __('Email') }}</label>
                <input type="email" id="cf-email" wire:model="email" autocomplete="email" placeholder="you@company.com">
                @error('email') <span class="contact-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="contact-form__field">
                <label for="cf-url">{{ __('Store or website') }} <span class="contact-form__optional">{{ __('(optional)') }}</span></label>
                <input type="url" id="cf-url" wire:model="website_url" autocomplete="url" placeholder="https://your-shop.etsy.com">
                @error('website_url') <span class="contact-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="contact-form__field">
                <label for="cf-message">{{ __('Message') }}</label>
                <textarea id="cf-message" wire:model="message" rows="5" placeholder="{{ __('Tell us about your products and where you sell.') }}"></textarea>
                @error('message') <span class="contact-form__error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn--coral" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Send message') }}</span>
                <span wire:loading wire:target="save">{{ __('Sending') }}&hellip;</span>
            </button>

            <x-web.privacy-consent />
        </form>
    @endif
</div>
