<div class="journey">
    @php
        $flash = session('starter_status');
    @endphp
    @if ($flash === 'signed' || $flash === 'paid')
        <div class="journey-flash">
            <svg class="journey-flash__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>{{ $flash === 'signed' ? __('Mandate signed. Next: upload your documents.') : __('Payment received. Your file is complete.') }}</span>
        </div>
    @endif

    @error('journey') <div class="funnel-form__error journey-error">{{ $message }}</div> @enderror

    @unless (in_array($step, ['done', 'cancelled'], true))
        @php
            $labels = ['sign' => __('Read & Sign'), 'documents' => __('Documents'), 'payment' => __('Payment')];
            $order = array_keys($labels);
            $currentIndex = array_search($step, $order, true);
        @endphp
        <ol class="journey-progress">
            @foreach ($labels as $key => $label)
                <li @class([
                    'journey-progress__step',
                    'is-done' => $currentIndex !== false && $loop->index < $currentIndex,
                    'is-current' => $loop->index === $currentIndex,
                ])>
                    <span class="journey-progress__num">{{ $loop->iteration }}</span>
                    <span class="journey-progress__label">{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    @endunless

    @if ($step === 'sign')
        <div class="journey-panel">
            <h2 class="journey-panel__title">{{ __('Sign your Responsible Person mandate') }}</h2>
            <p class="journey-panel__text">{{ __('This mandate authorises Festilaw to act as your official GPSR Responsible Person in the EU. You\'ll be taken to our secure signing partner and brought right back here.') }}</p>
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

            <button type="button" class="btn btn--coral" wire:click="sign" wire:loading.attr="disabled" wire:target="sign">
                <span wire:loading.remove wire:target="sign">{{ __('Sign the mandate') }}</span>
                <span wire:loading wire:target="sign">{{ __('Redirecting') }}&hellip;</span>
            </button>

            {{-- Retour OU reprise avec une signature en cours : on verifie le statut en silence (sans webhook). --}}
            @if ($autoConfirm)
                <div wire:init="autoConfirmSignature"></div>
            @endif
            @if ($signatureStarted)
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
                <span wire:loading.remove wire:target="submitDocuments">{{ __('Continue to payment') }}</span>
                <span wire:loading wire:target="submitDocuments">{{ __('Saving') }}&hellip;</span>
            </button>
        </div>

    @elseif ($step === 'payment')
        @php $amount = '€'.number_format($amountCents / 100, $amountCents % 100 === 0 ? 0 : 2); @endphp
        <div class="journey-panel">
            <h2 class="journey-panel__title">{{ __('Pay & activate') }}</h2>

            @if (! $paymentStarted)
                {{-- Aucun paiement lance : le formulaire de paiement classique. --}}
                <p class="journey-panel__text">{{ __('Your file is complete. Pay your Creator Pack subscription to activate your EU Responsible Person.') }}</p>
                <div class="journey-amount">
                    <span class="journey-amount__value">{{ $amount }}</span>
                    <span class="journey-amount__period">{{ __('per year') }}</span>
                </div>
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
            <h3 class="funnel-success__title">{{ __('Your Creator Pack is active.') }}</h3>
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
