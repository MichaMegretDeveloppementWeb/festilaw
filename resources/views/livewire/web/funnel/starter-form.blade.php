<div>
    @if ($sent)
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="funnel-success__title">Your file is open.</h3>
            <p class="funnel-success__text">We've received your details and will be in touch to guide you through the next steps: signing your mandate, uploading your documents and paying securely.</p>
            <a href="{{ route('home') }}" class="btn btn--outline-dark btn--sm">Back to home</a>
        </div>
    @else
        <form wire:submit="submit" class="funnel-form" novalidate>
            @error('form') <div class="funnel-form__error">{{ $message }}</div> @enderror

            <div class="hp-field" aria-hidden="true">
                <label for="sf-hp">Leave this field empty</label>
                <input type="text" id="sf-hp" wire:model="hp" tabindex="-1" autocomplete="off">
            </div>

            <div class="funnel-form__row funnel-form__row--two">
                <div class="funnel-form__field">
                    <label for="sf-company">Company</label>
                    <input type="text" id="sf-company" wire:model="company_name" placeholder="Your company">
                    @error('company_name') <span class="funnel-form__error">{{ $message }}</span> @enderror
                </div>
                <div class="funnel-form__field">
                    <label for="sf-reg">Company registration no. <span class="funnel-form__optional">(optional)</span></label>
                    <input type="text" id="sf-reg" wire:model="company_registration_number" placeholder="EIN / VAT / Business ID">
                </div>
            </div>

            <div class="funnel-form__row funnel-form__row--two">
                <div class="funnel-form__field">
                    <label for="sf-first">First name</label>
                    <input type="text" id="sf-first" wire:model="first_name" autocomplete="given-name" placeholder="First name">
                    @error('first_name') <span class="funnel-form__error">{{ $message }}</span> @enderror
                </div>
                <div class="funnel-form__field">
                    <label for="sf-last">Last name <span class="funnel-form__optional">(optional)</span></label>
                    <input type="text" id="sf-last" wire:model="last_name" autocomplete="family-name" placeholder="Last name">
                </div>
            </div>

            <div class="funnel-form__field">
                <label for="sf-email">Email</label>
                <input type="email" id="sf-email" wire:model="email" autocomplete="email" placeholder="you@company.com">
                @error('email') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="funnel-form__field">
                <label for="sf-url">Store or website <span class="funnel-form__optional">(optional)</span></label>
                <input type="url" id="sf-url" wire:model="website_url" placeholder="https://your-shop.com">
                @error('website_url') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn--coral" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Open my file</span>
                <span wire:loading wire:target="submit">Opening&hellip;</span>
            </button>
        </form>
    @endif
</div>
