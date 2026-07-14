<div>
    @if ($sent)
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="funnel-success__title">Audit requested.</h3>
            <p class="funnel-success__text">We'll email you the secure link to pay your &euro;75 audit fee and book your consultation. The fee is deducted from your final contract.</p>
            <a href="{{ route('home') }}" class="btn btn--outline-dark btn--sm">Back to home</a>
        </div>
    @else
        <form wire:submit="submit" class="funnel-form" novalidate>
            <div class="funnel-form__row funnel-form__row--two">
                <div class="funnel-form__field">
                    <label for="scf-company">Company</label>
                    <input type="text" id="scf-company" wire:model="company_name" placeholder="Your company">
                    @error('company_name') <span class="funnel-form__error">{{ $message }}</span> @enderror
                </div>
                <div class="funnel-form__field">
                    <label for="scf-name">Your name <span class="funnel-form__optional">(optional)</span></label>
                    <input type="text" id="scf-name" wire:model="first_name" autocomplete="given-name" placeholder="First name">
                </div>
            </div>

            <div class="funnel-form__field">
                <label for="scf-email">Email</label>
                <input type="email" id="scf-email" wire:model="email" autocomplete="email" placeholder="you@company.com">
                @error('email') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="funnel-form__field">
                <label for="scf-url">Store or website <span class="funnel-form__optional">(optional)</span></label>
                <input type="url" id="scf-url" wire:model="website_url" placeholder="https://your-shop.com">
                @error('website_url') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="funnel-form__field">
                <label for="scf-products">What do you sell? <span class="funnel-form__optional">(optional)</span></label>
                <input type="text" id="scf-products" wire:model="product_types" placeholder="e.g. electronics, toys">
            </div>

            <button type="submit" class="btn btn--coral" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Request my audit</span>
                <span wire:loading wire:target="submit">Sending&hellip;</span>
            </button>
        </form>
    @endif
</div>
