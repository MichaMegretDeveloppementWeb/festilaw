<div>
    @if ($sent)
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>
            </div>
            <h3 class="funnel-success__title">{{ __('Check your inbox') }}</h3>
            <p class="funnel-success__text">{{ __('If a Festilaw file exists for that email, we\'ve just sent a secure link to access it. It can take a minute to arrive.') }}</p>
            <a href="{{ route('home') }}" class="btn btn--outline-dark btn--sm">{{ __('Back to home') }}</a>
        </div>
    @else
        <form wire:submit="submit" class="funnel-form" novalidate>
            @error('form') <div class="funnel-form__error">{{ $message }}</div> @enderror

            <div class="hp-field" aria-hidden="true">
                <label for="af-hp">{{ __('Leave this field empty') }}</label>
                <input type="text" id="af-hp" wire:model="hp" tabindex="-1" autocomplete="off">
            </div>

            <div class="funnel-form__field">
                <label for="af-email">{{ __('Your email') }}</label>
                <input type="email" id="af-email" wire:model="email" autocomplete="email" placeholder="you@company.com">
                @error('email') <span class="funnel-form__error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn--coral" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">{{ __('Email me my link') }}</span>
                <span wire:loading wire:target="submit">{{ __('Sending') }}&hellip;</span>
            </button>
        </form>
    @endif
</div>
