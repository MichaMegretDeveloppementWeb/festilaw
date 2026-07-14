<div>
    @if ($sent)
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="funnel-success__title">Thanks, we've got your details.</h3>
            <p class="funnel-success__text">We'll reach out shortly to continue your Pro Pack setup.</p>
            <a href="{{ route('home') }}" class="btn btn--outline-dark btn--sm">Back to home</a>
        </div>
    @else
        <form wire:submit="submit" class="funnel-form" novalidate>
            <div class="funnel-form__row funnel-form__row--two">
                <div class="funnel-form__field">
                    <label for="pf-company">Company</label>
                    <input type="text" id="pf-company" wire:model="company_name" placeholder="Your company">
                    @error('company_name') <span class="funnel-form__error">{{ $message }}</span> @enderror
                </div>
                <div class="funnel-form__field">
                    <label for="pf-name">Your name <span class="funnel-form__optional">(optional)</span></label>
                    <input type="text" id="pf-name" wire:model="first_name" autocomplete="given-name" placeholder="First name">
                </div>
            </div>

            <div class="funnel-form__field">
                <label for="pf-email">Email</label>
                <input type="email" id="pf-email" wire:model="email" autocomplete="email" placeholder="you@company.com">
                @error('email') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="funnel-form__field">
                <label for="pf-url">Store or website <span class="funnel-form__optional">(optional)</span></label>
                <input type="url" id="pf-url" wire:model="website_url" placeholder="https://your-shop.com">
                @error('website_url') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="funnel-form__field">
                <label for="pf-products">What do you sell? <span class="funnel-form__optional">(optional)</span></label>
                <input type="text" id="pf-products" wire:model="product_types" placeholder="e.g. home decor, apparel">
            </div>

            <button type="submit" class="btn btn--coral" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Continue</span>
                <span wire:loading wire:target="submit">Sending&hellip;</span>
            </button>
        </form>
    @endif
</div>
