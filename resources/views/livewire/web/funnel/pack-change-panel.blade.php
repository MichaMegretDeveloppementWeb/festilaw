@use('App\Data\Web\Starter\PackChangePanelData', 'Panel')
@php
    $euros = fn (int $cents): string => '€'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    $other = __($panel->otherPackLabel);
    $current = __($panel->currentPackLabel);
    $quote = $euros($panel->quoteCents);
    $otherPrice = $euros($panel->otherAnnualCents);
    $finalising = $confirmingSignature && ! $signatureTimedOut;
@endphp

{{-- Changement de pack apres paiement (SC12) : passage au Pro (mandat Pro signe ici, puis difference payee). --}}
<div class="pack-change" @if ($panel->mode === Panel::NONE && ! session('pack_status')) hidden @endif>
    @if (session('pack_status') === 'signed')
        <div class="journey-flash">
            <svg class="journey-flash__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>{{ __('Your :pack mandate is signed. Last step: pay the difference.', ['pack' => $other]) }}</span>
        </div>
    @elseif (session('pack_status') === 'upgrade_cancelled')
        <p class="journey-note">{{ __('No change: you keep the :pack.', ['pack' => $current]) }}</p>
    @endif

    @error('pack') <div class="funnel-form__error journey-error">{{ $message }}</div> @enderror

    @if ($panel->mode === Panel::UPGRADE_OFFER)
        @if ($confirmingUpgrade)
            <div class="journey-switch">
                <p class="journey-switch__title">{{ __('Switch to the :pack?', ['pack' => $other]) }}</p>
                <p>{{ __('You pay :amount today: the difference with your :current for the rest of :year. From 1 January, your plan renews at :price per year.', ['amount' => $quote, 'current' => $current, 'year' => $panel->year, 'price' => $otherPrice]) }}</p>
                <p>{{ __('You\'ll first sign a new mandate for the :pack, right on this page.', ['pack' => $other]) }}</p>
                <div class="journey-switch__actions">
                    <button type="button" class="btn btn--coral btn--sm" wire:click="startUpgrade" wire:loading.attr="disabled" wire:target="startUpgrade">
                        <span wire:loading.remove wire:target="startUpgrade">{{ __('Switch to the :pack', ['pack' => $other]) }}</span>
                        <span wire:loading wire:target="startUpgrade">{{ __('Switching') }}&hellip;</span>
                    </button>
                    <button type="button" class="btn btn--outline-dark btn--sm" wire:click="$set('confirmingUpgrade', false)">{{ __('Keep the :pack', ['pack' => $current]) }}</button>
                </div>
            </div>
        @else
            <p class="journey-switch-link">{{ __('More than 9 products?') }} <button type="button" wire:click="$set('confirmingUpgrade', true)">{{ __('Switch to the :pack', ['pack' => $other]) }}</button></p>
        @endif
    @elseif ($panel->mode === Panel::UPGRADE_SIGN)
        <div class="journey-switch">
            <p class="journey-switch__title">{{ __('Switching to the :pack', ['pack' => $other]) }}</p>
            <p>{{ __('Step 1 of 2: sign your :pack mandate, in a window right on this page.', ['pack' => $other]) }}</p>
            @if ($panel->signatureDeclined)
                <p class="journey-note journey-note--warn">{{ __('The previous signature was declined. You can restart it below.') }}</p>
            @endif

            @if ($finalising)
                <div class="journey-processing" wire:poll.2s="pollSignature">
                    <span class="journey-processing__spinner" aria-hidden="true"></span>
                    <p class="journey-panel__text">{{ __('Finalising your signature · this page updates on its own in a few seconds.') }}</p>
                </div>
            @else
                @if ($confirmingSignature)
                    <p class="journey-note">{{ __('Your signature is taking a little longer to be confirmed. Check again below in a moment.') }}</p>
                @endif
                <div class="journey-switch__actions">
                    <button type="button" class="btn btn--coral btn--sm" wire:click="sign" wire:loading.attr="disabled" wire:target="sign">
                        <span wire:loading.remove wire:target="sign">{{ __('Sign the mandate') }}</span>
                        <span wire:loading wire:target="sign">{{ __('Opening') }}&hellip;</span>
                    </button>
                    @if ($panel->signatureStarted)
                        <button type="button" class="btn btn--outline-dark btn--sm" wire:click="confirmSignature" wire:loading.attr="disabled" wire:target="confirmSignature">
                            <span wire:loading.remove wire:target="confirmSignature">{{ __('I have signed · check now') }}</span>
                            <span wire:loading wire:target="confirmSignature">{{ __('Checking') }}&hellip;</span>
                        </button>
                    @endif
                </div>
            @endif

            @if ($autoConfirm)
                <div wire:init="autoConfirmSignature"></div>
            @endif

            <p class="journey-switch-link"><button type="button" wire:click="cancelUpgrade" wire:loading.attr="disabled" wire:target="cancelUpgrade">{{ __('Keep the :pack', ['pack' => $current]) }}</button></p>
        </div>
    @elseif ($panel->mode === Panel::UPGRADE_PAY)
        <div class="journey-switch">
            <p class="journey-switch__title">{{ __('Switching to the :pack', ['pack' => $other]) }}</p>
            <p>{{ __('Step 2 of 2: pay :amount, the difference for the rest of :year. Your :pack starts as soon as the payment is confirmed.', ['amount' => $quote, 'year' => $panel->year, 'pack' => $other]) }}</p>
            <div class="journey-switch__actions">
                <button type="button" class="btn btn--coral btn--sm" wire:click="pay" wire:loading.attr="disabled" wire:target="pay">
                    <span wire:loading.remove wire:target="pay">{{ __('Pay :amount', ['amount' => $quote]) }}</span>
                    <span wire:loading wire:target="pay">{{ __('Redirecting') }}&hellip;</span>
                </button>
                <button type="button" class="btn btn--outline-dark btn--sm" wire:click="cancelUpgrade" wire:loading.attr="disabled" wire:target="cancelUpgrade">{{ __('Keep the :pack', ['pack' => $current]) }}</button>
            </div>
        </div>
    @elseif ($panel->mode === Panel::UPGRADE_PROCESSING)
        <p class="journey-note">{{ __('Your payment is being confirmed. Your :pack starts as soon as it is.', ['pack' => $other]) }}</p>
    @endif
</div>

@script
<script>
    // Signature integree du nouveau mandat (meme mecanique que le parcours) : l'iframe SignWell s'ouvre en
    // modal sur cette page ; l'evenement "completed" declenche la confirmation cote serveur (le statut reste
    // verifie chez le prestataire, jamais sur la seule foi du navigateur).
    let signWellScript = null;

    const loadSignWell = () => signWellScript ??= new Promise((resolve, reject) => {
        if (window.SignWellEmbed) {
            resolve();

            return;
        }

        const script = document.createElement('script');
        script.src = 'https://static.signwell.com/assets/embedded.js';
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            signWellScript = null;
            reject(new Error('SignWell embed script failed to load'));
        };
        document.head.appendChild(script);
    });

    $wire.$on('open-signing', async (event) => {
        const url = event?.url ?? event?.detail?.url;
        if (!url) {
            return;
        }

        try {
            await loadSignWell();
        } catch (error) {
            $wire.signingUnavailable();

            return;
        }

        new window.SignWellEmbed({
            url,
            allowRedirect: false,
            events: {
                completed: () => $wire.signingCompleted(),
                declined: () => $wire.signingDeclined(),
                closed: () => $wire.autoConfirmSignature(),
                error: () => $wire.signingUnavailable(),
            },
        }).open();
    });
</script>
@endscript
