<div class="journey">
    @php
        $flashMessage = match (session('starter_status')) {
            'documents_saved' => $currentStep === 'payment'
                ? __('Documents saved. Last step: payment.')
                : __('Documents saved. Next: sign your mandate.'),
            'signed' => __('Mandate signed. Last step: payment.'),
            'pack_changed' => __('You\'ve switched to the :pack. Your details and documents have been carried over.', ['pack' => __($packLabel)]),
            'paid' => __('Payment received. Your file is complete.'),
            'document_replaced' => __('Document replaced.'),
            default => null,
        };
    @endphp
    @if ($flashMessage)
        <div class="journey-flash">
            <svg class="journey-flash__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>{{ $flashMessage }}</span>
        </div>
    @endif

    @error('journey') <div class="funnel-form__error journey-error">{{ $message }}</div> @enderror

    {{-- Changement de pack avant paiement : le dossier est remplace par un nouveau au pack choisi (infos et
         documents repris, mandat a re-signer). Masque des qu'un paiement est lance. --}}
    @if ($canChangePack)
        @php
            $otherPack = __($otherPackLabel);
            $otherPrice = '€'.number_format($otherPackAnnualCents / 100, $otherPackAnnualCents % 100 === 0 ? 0 : 2);
        @endphp
        @if ($confirmingPackChange)
            <div class="journey-switch">
                <p class="journey-switch__title">{{ __('Switch to the :pack?', ['pack' => $otherPack]) }}</p>
                <p>{{ __('The :pack is :price/year. Your details and documents are carried over to a new file for this plan.', ['pack' => $otherPack, 'price' => $otherPrice]) }}</p>
                @if ($contractSigned)
                    <p>{{ __('You\'ve already signed your mandate for the :current: you\'ll sign a new one for the :pack.', ['current' => __($packLabel), 'pack' => $otherPack]) }}</p>
                @endif
                <p>{{ __('Your current link will stop working: we\'ll email you the new one.') }}</p>
                <div class="journey-switch__actions">
                    <button type="button" class="btn btn--coral btn--sm" wire:click="changePack" wire:loading.attr="disabled" wire:target="changePack">
                        <span wire:loading.remove wire:target="changePack">{{ __('Switch to the :pack', ['pack' => $otherPack]) }}</span>
                        <span wire:loading wire:target="changePack">{{ __('Switching') }}&hellip;</span>
                    </button>
                    <button type="button" class="btn btn--outline-dark btn--sm" wire:click="$set('confirmingPackChange', false)">{{ __('Keep the :pack', ['pack' => __($packLabel)]) }}</button>
                </div>
            </div>
        @else
            <p class="journey-switch-link">{{ __('Not the right plan?') }} <button type="button" wire:click="$set('confirmingPackChange', true)">{{ __('Switch to the :pack', ['pack' => $otherPack]) }}</button></p>
        @endif
    @endif

    @unless (in_array($currentStep, ['done', 'cancelled'], true))
        @php
            $labels = ['documents' => __('Documents'), 'sign' => __('Read & Sign'), 'payment' => __('Payment')];
        @endphp
        {{-- Etapes dans l'ordre du parcours ; "faite" d'apres les faits (pieces completes, mandat signe),
             pas d'apres sa position : on peut revoir toute etape faite, jamais une etape a venir. --}}
        <ol class="journey-progress">
            @foreach ($steps as $key)
                @php
                    $isCurrent = $key === $currentStep;
                    $isDone = ! $isCurrent && in_array($key, $completedSteps, true);
                    $navigable = $isCurrent || $isDone;
                @endphp
                <li wire:key="progress-{{ $key }}" @class([
                        'journey-progress__step',
                        'is-done' => $isDone,
                        'is-current' => $isCurrent,
                        'is-navigable' => $navigable,
                        'is-viewing' => $reviewing && $key === $step,
                    ])
                    @if ($navigable) wire:click="goToStep('{{ $key }}')" role="button" tabindex="0" @endif>
                    <span class="journey-progress__num">{{ $loop->iteration }}</span>
                    <span class="journey-progress__label">{{ $labels[$key] }}</span>
                </li>
            @endforeach
        </ol>
    @endunless

    @if ($reviewing)
        {{-- Revue en LECTURE SEULE d'une etape deja franchie (clic sur la barre de progression). Les gardes
             d'action restent sur l'etape reelle : impossible de declencher une action hors sequence ici. --}}
        <div class="journey-panel">
            @if ($step === 'sign')
                <h2 class="journey-panel__title">{{ __('Sign your Responsible Person mandate') }}</h2>
                <p class="journey-panel__text">{{ __('You have already signed your Responsible Person mandate. This step is done.') }}</p>
                @if ($mandateAvailable)
                    <ul class="journey-docs">
                        <li class="journey-doc">
                            <svg class="journey-doc__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span class="journey-doc__meta"><span class="journey-doc__name">{{ __('Signed mandate') }}</span></span>
                            <span class="journey-doc__actions">
                                <a href="{{ $mandateUrl }}" class="journey-doc__btn">{{ __('Download') }}</a>
                            </span>
                        </li>
                    </ul>
                @endif
            @elseif ($step === 'documents')
                <h2 class="journey-panel__title">{{ __('Upload your documents') }}</h2>
                <p class="journey-panel__text">{{ __('Your documents are saved. Download one to check it, or replace it if you uploaded the wrong file.') }}</p>
                <ul class="journey-docs">
                    @foreach ($reviewDocuments as $doc)
                        @php $staged = $replacementsStaged[$doc['type']] ?? null; @endphp
                        <li class="journey-doc" wire:key="review-doc-{{ $doc['type'] }}">
                            <svg class="journey-doc__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span class="journey-doc__meta">
                                <span class="journey-doc__name">{{ $doc['label'] }}</span>
                                @if ($staged)
                                    <span class="journey-doc__sub journey-doc__sub--new">{{ $staged['name'] }} · {{ __('new file, not saved yet') }}</span>
                                @else
                                    <span class="journey-doc__sub">{{ $doc['filename'] }}</span>
                                @endif
                            </span>
                            <span class="journey-doc__actions">
                                @if ($staged)
                                    <button type="button" class="journey-doc__btn journey-doc__btn--primary" wire:click="replaceDocument('{{ $doc['type'] }}')" wire:loading.attr="disabled" wire:target="replaceDocument('{{ $doc['type'] }}')">
                                        <span wire:loading.remove wire:target="replaceDocument('{{ $doc['type'] }}')">{{ __('Confirm') }}</span>
                                        <span wire:loading wire:target="replaceDocument('{{ $doc['type'] }}')">{{ __('Saving') }}&hellip;</span>
                                    </button>
                                    <button type="button" class="journey-doc__btn" wire:click="cancelReplacement('{{ $doc['type'] }}')">{{ __('Cancel') }}</button>
                                @else
                                    <a href="{{ $doc['downloadUrl'] }}" class="journey-doc__btn">{{ __('Download') }}</a>
                                    <label class="journey-doc__btn journey-doc__replace" wire:loading.class="is-busy" wire:target="replacements.{{ $doc['type'] }}">
                                        <span wire:loading.remove wire:target="replacements.{{ $doc['type'] }}">{{ __('Replace') }}</span>
                                        <span wire:loading wire:target="replacements.{{ $doc['type'] }}">{{ __('Uploading') }}&hellip;</span>
                                        <input type="file" wire:model="replacements.{{ $doc['type'] }}" accept="{{ $acceptAttr }}">
                                    </label>
                                @endif
                            </span>
                            @error("replacements.{$doc['type']}") <p class="dropzone-field__error">{{ $message }}</p> @enderror
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="journey-review">
                <span>{{ __('This step is done · go back to the current step to continue.') }}</span>
                <button type="button" class="btn btn--outline-dark btn--sm" wire:click="goToStep('{{ $currentStep }}')">{{ __('Back to the current step') }}</button>
            </div>
        </div>

    @elseif ($step === 'sign')
        <div class="journey-panel">
            <h2 class="journey-panel__title">{{ __('Sign your Responsible Person mandate') }}</h2>
            <p class="journey-panel__text">{{ __('This mandate authorises Festilaw to act as your official GPSR Responsible Person in the EU. You\'ll sign it securely with our signing partner, in a window right on this page.') }}</p>
            @if ($contractDeclined)
                <p class="journey-note journey-note--warn">{{ __('The previous signature was declined. You can restart it below.') }}</p>
            @endif

            @unless ($signatureStarted)
                <div class="journey-mandate">
                    <p class="journey-mandate__intro">{{ __('Confirm the details that will appear on your mandate:') }}</p>
                    <div class="funnel-form">
                        <div class="funnel-form__field">
                            <label>{{ __('Company') }}</label>
                            <input type="text" value="{{ $submission->company_name }}" readonly>
                        </div>
                        <div class="funnel-form__field">
                            <label for="mandate-place">{{ __('City and country of incorporation') }}</label>
                            <input type="text" id="mandate-place" wire:model="incorporationPlace" placeholder="{{ __('e.g. Toronto, Canada') }}">
                            @error('incorporationPlace') <span class="funnel-form__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="funnel-form__field">
                            <label for="mandate-year">{{ __('Year founded') }}</label>
                            <input type="text" id="mandate-year" wire:model="foundingYear" inputmode="numeric" placeholder="{{ __('e.g. 2015') }}">
                            @error('foundingYear') <span class="funnel-form__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="funnel-form__field">
                            <label for="mandate-activity">{{ __('Main business activity') }}</label>
                            <textarea id="mandate-activity" wire:model="activity" rows="2" placeholder="{{ __('e.g. the design and online sale of home decor') }}"></textarea>
                            @error('activity') <span class="funnel-form__error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            @endunless

            @php $finalising = $confirmingSignature && ! $signatureTimedOut; @endphp
            @if ($finalising)
                {{-- L'iframe a signale la signature : on attend que le prestataire la confirme (boucle bornee). --}}
                <div class="journey-processing" wire:poll.2s="pollSignature">
                    <span class="journey-processing__spinner" aria-hidden="true"></span>
                    <p class="journey-panel__text">{{ __('Finalising your signature · this page updates on its own in a few seconds.') }}</p>
                </div>
            @else
                @if ($confirmingSignature)
                    <p class="journey-note">{{ __('Your signature is taking a little longer to be confirmed. Check again below in a moment.') }}</p>
                @endif
                <button type="button" class="btn btn--coral" wire:click="sign" wire:loading.attr="disabled" wire:target="sign">
                    <span wire:loading.remove wire:target="sign">{{ __('Sign the mandate') }}</span>
                    <span wire:loading wire:target="sign">{{ __('Opening') }}&hellip;</span>
                </button>
            @endif

            {{-- Reprise avec une signature en cours : on verifie le statut en silence (sans webhook). --}}
            @if ($autoConfirm)
                <div wire:init="autoConfirmSignature"></div>
            @endif
            @if ($signatureStarted && ! $finalising)
                <button type="button" class="btn btn--outline-dark btn--sm" wire:click="confirmSignature" wire:loading.attr="disabled" wire:target="confirmSignature">
                    <span wire:loading.remove wire:target="confirmSignature">{{ __('I have signed · check now') }}</span>
                    <span wire:loading wire:target="confirmSignature">{{ __('Checking') }}&hellip;</span>
                </button>
            @endif
        </div>

    @elseif ($step === 'documents')
        <div class="journey-panel">
            <h2 class="journey-panel__title">{{ __('Upload your documents') }}</h2>
            <p class="journey-panel__text">{{ __('Drop your files below or click to browse. PDF, JPG, PNG or WEBP, up to 10 MB each. Nothing is saved until you continue.') }}</p>

            <div class="dropzones">
                @foreach ($requiredDocuments as $doc)
                    @php $file = $deposits[$doc->value] ?? null; @endphp
                    <div @class(['dropzone-field', 'is-invalid' => $errors->has("documents.{$doc->value}")])>
                        <div class="dropzone-field__label">{{ $doc->label() }}</div>
                        <p class="dropzone-field__hint">{{ $doc->hint() }}</p>

                        @if ($file)
                            <div class="dropzone-file">
                                <svg class="dropzone-file__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <div class="dropzone-file__meta">
                                    <span class="dropzone-file__name">{{ $file['name'] }}</span>
                                    @if ($file['size'] !== null)
                                        <span class="dropzone-file__size">{{ number_format($file['size'] / 1024, 0) }} KB</span>
                                    @endif
                                </div>
                                <button type="button" class="dropzone-file__remove" wire:click="removeDocument('{{ $doc->value }}')" aria-label="{{ __('Remove :document', ['document' => $doc->label()]) }}">&times;</button>
                            </div>
                        @else
                            <div class="dropzone"
                                 x-data="{ over: false }"
                                 @dragover.prevent="over = true"
                                 @dragleave.prevent="over = false"
                                 @drop.prevent="over = false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change'))"
                                 @click="$refs.input.click()"
                                 :class="{ 'is-over': over }"
                                 wire:loading.class="is-busy" wire:target="documents.{{ $doc->value }}">
                                <input type="file" x-ref="input" class="dropzone__input" wire:model="documents.{{ $doc->value }}" accept="{{ $acceptAttr }}">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span class="dropzone__text" wire:loading.remove wire:target="documents.{{ $doc->value }}"><strong>{{ __('Drag & drop') }}</strong> {{ __('or') }} <span class="dropzone__browse">{{ __('browse') }}</span></span>
                                <span class="dropzone__hint" wire:loading.remove wire:target="documents.{{ $doc->value }}">{{ __('PDF, JPG, PNG or WEBP · up to 10 MB') }}</span>
                                <span class="dropzone__uploading" wire:loading wire:target="documents.{{ $doc->value }}">{{ __('Uploading') }}&hellip;</span>
                            </div>
                        @endif

                        @error("documents.{$doc->value}")
                            <p class="dropzone-field__error">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            @error('documents_submit') <div class="funnel-form__error journey-error">{{ $message }}</div> @enderror

            <button type="button" class="btn btn--coral" wire:click="submitDocuments" wire:loading.attr="disabled" wire:target="submitDocuments">
                {{-- Un dossier deja signe (ancien ordre) passe directement au paiement. --}}
                <span wire:loading.remove wire:target="submitDocuments">{{ in_array('sign', $completedSteps, true) ? __('Continue to payment') : __('Continue to signature') }}</span>
                <span wire:loading wire:target="submitDocuments">{{ __('Saving') }}&hellip;</span>
            </button>
        </div>

    @elseif ($step === 'payment')
        @php
            $amount = '€'.number_format($amountCents / 100, $amountCents % 100 === 0 ? 0 : 2);
            $annual = '€'.number_format($annualCents / 100, $annualCents % 100 === 0 ? 0 : 2);
        @endphp
        <div class="journey-panel">
            <h2 class="journey-panel__title">{{ __('Pay & activate') }}</h2>

            @if ($failedPayment)
                {{-- Un paiement a ete note echoue : avant de re-payer, on propose de re-interroger le
                     prestataire (source de verite). S'il a en fait ete paye, on corrige sans double-debit. --}}
                <div class="journey-note">
                    <p>{{ __('Your last payment attempt (reference :ref) was recorded as failed. If you think it actually went through, check with :provider before paying again.', ['ref' => $failedPayment->provider_reference, 'provider' => $failedPayment->providerLabel()]) }}</p>
                    <button type="button" class="btn btn--outline-dark btn--sm" wire:click="recheckPayment" wire:loading.attr="disabled" wire:target="recheckPayment">
                        <span wire:loading.remove wire:target="recheckPayment">{{ __('Check my payment on :provider', ['provider' => $failedPayment->providerLabel()]) }}</span>
                        <span wire:loading wire:target="recheckPayment">{{ __('Checking') }}&hellip;</span>
                    </button>
                </div>
            @endif

            @if (! $paymentStarted)
                {{-- Aucun paiement lance : le formulaire de paiement classique. --}}
                <p class="journey-panel__text">{{ __('Your file is complete. Pay your :pack subscription to activate your EU Responsible Person.', ['pack' => __($packLabel)]) }}</p>
                <div class="journey-amount">
                    <span class="journey-amount__value">{{ $amount }}</span>
                    <span class="journey-amount__period">{{ __('due now') }}</span>
                </div>
                <p class="journey-amount__note">{{ __('Prorated for the rest of :year. The full fee is :annual/year, invoiced each January.', ['year' => $serviceYear, 'annual' => $annual]) }}</p>
                @if (count($paymentOptions) > 1)
                    <div class="journey-methods">
                        @foreach ($paymentOptions as $key => $label)
                            <label class="journey-method">
                                <input type="radio" wire:model="paymentProvider" value="{{ $key }}">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <button type="button" class="btn btn--coral" wire:click="pay" wire:loading.attr="disabled" wire:target="pay">
                    <span wire:loading.remove wire:target="pay">{{ __('Pay :amount securely', ['amount' => $amount]) }}</span>
                    <span wire:loading wire:target="pay">{{ __('Redirecting') }}&hellip;</span>
                </button>
            @else
                {{-- Paiement en vol : on confirme (boucle auto), sans re-proposer "Payer" => anti double-debit.
                     La boucle interroge le prestataire ; le webhook reste la source de verite en fond. --}}
                @if (! $paymentTimedOut)
                    <div class="journey-processing" wire:init="pollPayment" wire:poll.5s="pollPayment">
                        <span class="journey-processing__spinner" aria-hidden="true"></span>
                        <p class="journey-panel__text">{{ __('We\'re confirming your payment. Some payment methods take a moment to clear · this page updates on its own, no need to pay again.') }}</p>
                    </div>
                @else
                    <p class="journey-note">{{ __('Your payment is still being confirmed. We\'ll email you the moment it clears · you can safely close this page.') }}</p>
                @endif
                <button type="button" class="btn btn--outline-dark btn--sm" wire:click="pay" wire:loading.attr="disabled" wire:target="pay">
                    <span wire:loading.remove wire:target="pay">{{ __('Haven\'t finished paying? Resume') }}</span>
                    <span wire:loading wire:target="pay">{{ __('Redirecting') }}&hellip;</span>
                </button>
            @endif
        </div>

    @elseif ($step === 'done')
        {{-- Le parcours redirige normalement vers l'espace "mon dossier" ; ceci est un filet de secours. --}}
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="funnel-success__title">{{ __('Your :pack is active.', ['pack' => __($packLabel)]) }}</h3>
            <p class="funnel-success__text">{{ __('Your file is ready in your personal space, with your signed mandate and documents.') }}</p>
            <a href="{{ $myProjectUrl }}" class="btn btn--coral">{{ __('Go to my project') }}</a>
        </div>

    @elseif ($step === 'cancelled')
        <div class="journey-panel">
            <h2 class="journey-panel__title">{{ __('This file was cancelled') }}</h2>
            <p class="journey-panel__text">{{ __('Please get in touch if you\'d like to reopen it.') }}</p>
            <a href="{{ route('contact') }}" class="btn btn--outline-dark">{{ __('Contact us') }}</a>
        </div>
    @endif
</div>

@script
<script>
    // Signature integree : l'iframe SignWell s'ouvre en modal sur cette page, le signataire ne quitte
    // jamais le site. Le script SignWell est charge a la demande, une seule fois. Aucune redirection :
    // l'evenement "completed" declenche la confirmation cote serveur (le statut reste verifie chez le
    // prestataire, jamais sur la seule foi du navigateur).
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
