<div class="contact-form">
    @if ($sent)
        <div class="contact-form__success">
            <div class="contact-form__success-icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="contact-form__success-title">Thanks, we got your message.</h3>
            <p class="contact-form__success-text">Our team will get back to you shortly.</p>
            <button type="button" wire:click="$set('sent', false)" class="btn btn--outline-dark btn--sm">Send another</button>
        </div>
    @else
        <form wire:submit="save" class="contact-form__form" novalidate>
            <div class="contact-form__field">
                <label for="cf-name">Name</label>
                <input type="text" id="cf-name" wire:model="name" autocomplete="name" placeholder="Your name">
                @error('name') <span class="contact-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="contact-form__field">
                <label for="cf-email">Email</label>
                <input type="email" id="cf-email" wire:model="email" autocomplete="email" placeholder="you@company.com">
                @error('email') <span class="contact-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="contact-form__field">
                <label for="cf-url">Store or website <span class="contact-form__optional">(optional)</span></label>
                <input type="url" id="cf-url" wire:model="website_url" autocomplete="url" placeholder="https://your-shop.etsy.com">
                @error('website_url') <span class="contact-form__error">{{ $message }}</span> @enderror
            </div>

            <div class="contact-form__field">
                <label for="cf-message">Message</label>
                <textarea id="cf-message" wire:model="message" rows="5" placeholder="Tell us about your products and where you sell."></textarea>
                @error('message') <span class="contact-form__error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn--coral" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Send message</span>
                <span wire:loading wire:target="save">Sending&hellip;</span>
            </button>
        </form>
    @endif
</div>
