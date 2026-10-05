@use('App\Data\Web\Starter\PackChangePanelData', 'Panel')
@php
    $euros = fn (int $cents): string => '€'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    $other = __($panel->otherPackLabel);
    $current = __($panel->currentPackLabel);
    $quote = $euros($panel->quoteCents);
    $otherPrice = $euros($panel->otherAnnualCents);
    $finalising = $confirmingSignature && ! $signatureTimedOut;
    $flash = session('pack_status');
@endphp

{{-- Changement de pack apres paiement (SC12) : passage au Pro (mandat Pro signe ici, puis difference payee) et
     retour au Creator (demande validee par Festilaw, mandat Creator signe ici au renouvellement). --}}
<div class="pack-change" @if ($panel->mode === Panel::NONE && ! $flash) hidden @endif>
    @if ($flash === 'signed' || $flash === 'mandate_signed')
        <div class="journey-flash">
            <svg class="journey-flash__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>{{ $flash === 'signed'
                ? __('Your :pack mandate is signed. Last step: pay the difference.', ['pack' => $other])
                : __('Your :pack mandate is signed. You can now renew your plan.', ['pack' => $current]) }}</span>
        </div>
    @elseif ($flash === 'upgrade_cancelled')
        <p class="journey-note">{{ __('No change: you keep the :pack.', ['pack' => $current]) }}</p>
    @elseif ($flash === 'downgrade_withdrawn')
        <p class="journey-note">{{ __('Your request is withdrawn: you keep the :pack.', ['pack' => $current]) }}</p>
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
            @include('livewire.web.funnel.partials.pack-change-signature')
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
    @elseif ($panel->mode === Panel::MANDATE_SIGN)
        <div class="journey-switch">
            <p class="journey-switch__title">{{ __('Sign your :pack mandate', ['pack' => $current]) }}</p>
            <p>{{ __('Your file is now on the :pack. Sign your new mandate, in a window right on this page, then renew your plan.', ['pack' => $current]) }}</p>
            @include('livewire.web.funnel.partials.pack-change-signature')
        </div>
    @elseif ($panel->mode === Panel::DOWNGRADE_REQUESTED)
        <p class="journey-note">
            {{ __('Your request to switch to the :pack is with Festilaw: we\'ll confirm it by email.', ['pack' => $other]) }}
            <span class="journey-switch-link"><button type="button" wire:click="withdrawDowngrade">{{ __('Withdraw my request') }}</button></span>
        </p>
    @elseif ($panel->mode === Panel::DOWNGRADE_APPROVED)
        <p class="journey-note">
            {{ __('Confirmed: your file switches to the :pack from 1 January :year, at :price per year. You\'ll sign your new mandate when you renew.', ['pack' => $other, 'year' => $panel->effectiveYear, 'price' => $otherPrice]) }}
            <span class="journey-switch-link"><button type="button" wire:click="withdrawDowngrade" wire:confirm="{{ __('Keep the :pack and withdraw your request?', ['pack' => $current]) }}">{{ __('Withdraw my request') }}</button></span>
        </p>
    @elseif ($panel->mode === Panel::DOWNGRADE_OFFER || $panel->mode === Panel::DOWNGRADE_REJECTED)
        @if ($panel->mode === Panel::DOWNGRADE_REJECTED)
            <p class="journey-note journey-note--warn">{{ __('Your last request to switch to the :pack was not accepted. Contact us if you have any questions.', ['pack' => $other]) }}</p>
        @endif

        @if ($requestingDowngrade)
            <div class="journey-switch">
                <p class="journey-switch__title">{{ __('Switch to the :pack from your next renewal?', ['pack' => $other]) }}</p>
                <p>{{ __('Your :current stays active until 31 December :lastYear. From 1 January :year, your plan renews at :price per year, with a new :pack mandate to sign.', ['current' => $current, 'lastYear' => $panel->effectiveYear - 1, 'year' => $panel->effectiveYear, 'price' => $otherPrice, 'pack' => $other]) }}</p>
                <p>{{ __('Festilaw checks that your business is eligible before confirming.') }}</p>
                <div class="funnel-form">
                    <div class="funnel-form__field">
                        <label for="turnover-proof">{{ __('A recent proof of turnover') }}</label>
                        <input type="file" id="turnover-proof" wire:model="turnoverProof" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <div wire:loading wire:target="turnoverProof" class="journey-note">{{ __('Uploading') }}&hellip;</div>
                        @error('turnoverProof') <span class="funnel-form__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="funnel-form__field">
                        <label class="pack-change__attest">
                            <input type="checkbox" wire:model="eligibilityConfirmed">
                            <span>{{ __('I sell 9 products or fewer in the EU and my annual turnover is under €35,000.') }}</span>
                        </label>
                        @error('eligibilityConfirmed') <span class="funnel-form__error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="journey-switch__actions">
                    <button type="button" class="btn btn--coral btn--sm" wire:click="requestDowngrade" wire:loading.attr="disabled" wire:target="requestDowngrade,turnoverProof">
                        <span wire:loading.remove wire:target="requestDowngrade">{{ __('Send my request') }}</span>
                        <span wire:loading wire:target="requestDowngrade">{{ __('Sending') }}&hellip;</span>
                    </button>
                    <button type="button" class="btn btn--outline-dark btn--sm" wire:click="$set('requestingDowngrade', false)">{{ __('Keep the :pack', ['pack' => $current]) }}</button>
                </div>
            </div>
        @else
            <p class="journey-switch-link">{{ __('9 products or fewer?') }} <button type="button" wire:click="$set('requestingDowngrade', true)">{{ __('Request the :pack from your next renewal', ['pack' => $other]) }}</button></p>
        @endif
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
