<div>
    @if ($resent)
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>
            </div>
            <h3 class="funnel-success__title">{{ __('Check your inbox') }}</h3>
            @if ($resentActive)
                <p class="funnel-success__text">{!! __('You already have an active Creator Pack subscription. We\'ve emailed your secure link to :email to view your file and download your documents.', ['email' => '<strong>'.e($email).'</strong>']) !!}</p>
            @else
                <p class="funnel-success__text">{!! __('You already have an application in progress. We\'ve just emailed your secure link to :email so you can pick up right where you left off.', ['email' => '<strong>'.e($email).'</strong>']) !!}</p>
            @endif
            <a href="{{ route('home') }}" class="btn btn--outline-dark btn--sm">{{ __('Back to home') }}</a>
        </div>
    @elseif ($sent)
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="funnel-success__title">{{ __('Your file is open.') }}</h3>
            <p class="funnel-success__text">{{ __('We\'ve received your details and will be in touch to guide you through the next steps: signing your mandate, uploading your documents and paying securely.') }}</p>
            <a href="{{ route('home') }}" class="btn btn--outline-dark btn--sm">{{ __('Back to home') }}</a>
        </div>
    @else
        <form wire:submit="submit" class="funnel-form" novalidate>
            @error('form') <div class="funnel-form__error">{{ $message }}</div> @enderror

            <div class="hp-field" aria-hidden="true">
                <label for="sf-hp">{{ __('Leave this field empty') }}</label>
                <input type="text" id="sf-hp" wire:model="hp" tabindex="-1" autocomplete="off">
            </div>

            <div class="funnel-form__row funnel-form__row--two">
                <div class="funnel-form__field">
                    <label for="sf-company">{{ __('Company') }}</label>
                    <input type="text" id="sf-company" wire:model="company_name" placeholder="{{ __('Your company') }}">
                    @error('company_name') <span class="funnel-form__error">{{ $message }}</span> @enderror
                </div>
                <div class="funnel-form__field">
                    <label for="sf-reg">{{ __('Company registration no.') }} <span class="funnel-form__optional">{{ __('(optional)') }}</span></label>
                    <input type="text" id="sf-reg" wire:model="company_registration_number" placeholder="{{ __('EIN / VAT / Business ID') }}">
                </div>
            </div>

            <div class="funnel-form__row funnel-form__row--two">
                <div class="funnel-form__field">
                    <label for="sf-first">{{ __('First name') }}</label>
                    <input type="text" id="sf-first" wire:model="first_name" autocomplete="given-name" placeholder="{{ __('First name') }}">
                    @error('first_name') <span class="funnel-form__error">{{ $message }}</span> @enderror
                </div>
                <div class="funnel-form__field">
                    <label for="sf-last">{{ __('Last name') }} <span class="funnel-form__optional">{{ __('(optional)') }}</span></label>
                    <input type="text" id="sf-last" wire:model="last_name" autocomplete="family-name" placeholder="{{ __('Last name') }}">
                </div>
            </div>

            <div class="funnel-form__field">
                <label for="sf-email">{{ __('Email') }}</label>
                <input type="email" id="sf-email" wire:model="email" autocomplete="email" placeholder="you@company.com">
                @error('email') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="funnel-form__field">
                <label for="sf-url">{{ __('Store or website') }} <span class="funnel-form__optional">{{ __('(optional)') }}</span></label>
                <input type="url" id="sf-url" wire:model="website_url" placeholder="https://your-shop.com">
                @error('website_url') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn--coral" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">{{ __('Open my file') }}</span>
                <span wire:loading wire:target="submit">{{ __('Opening') }}&hellip;</span>
            </button>
        </form>
    @endif
</div>
