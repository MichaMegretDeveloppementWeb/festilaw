{{-- Signature integree d'un mandat depuis l'espace client (SC12) : montee au Pro ou nouveau pack au renouvellement. --}}
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
