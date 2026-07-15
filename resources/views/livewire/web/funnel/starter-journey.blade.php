<div class="journey">
    @php
        $flash = session('starter_status');
    @endphp
    @if ($flash === 'signed' || $flash === 'paid')
        <div class="journey-flash">
            <svg class="journey-flash__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>{{ $flash === 'signed' ? 'Mandate signed. Next: upload your documents.' : 'Payment received. Your file is complete.' }}</span>
        </div>
    @endif

    @error('journey') <div class="funnel-form__error journey-error">{{ $message }}</div> @enderror

    @unless (in_array($step, ['done', 'cancelled'], true))
        @php
            $labels = ['sign' => 'Sign', 'documents' => 'Documents', 'payment' => 'Payment'];
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
            <h2 class="journey-panel__title">Sign your Responsible Person mandate</h2>
            <p class="journey-panel__text">This mandate authorises Festilaw to act as your official GPSR Responsible Person in the EU. You'll be taken to our secure signing partner and brought right back here.</p>
            @if ($contractDeclined)
                <p class="journey-note journey-note--warn">The previous signature was declined. You can restart it below.</p>
            @endif
            <button type="button" class="btn btn--coral" wire:click="sign" wire:loading.attr="disabled" wire:target="sign">
                <span wire:loading.remove wire:target="sign">Sign the mandate</span>
                <span wire:loading wire:target="sign">Redirecting&hellip;</span>
            </button>

            {{-- Retour OU reprise avec une signature en cours : on verifie le statut en silence (sans webhook). --}}
            @if ($autoConfirm)
                <div wire:init="autoConfirmSignature"></div>
            @endif
            @if ($signatureStarted)
                <button type="button" class="btn btn--outline-dark btn--sm" wire:click="confirmSignature" wire:loading.attr="disabled" wire:target="confirmSignature">
                    <span wire:loading.remove wire:target="confirmSignature">I have signed &middot; check now</span>
                    <span wire:loading wire:target="confirmSignature">Checking&hellip;</span>
                </button>
            @endif
        </div>

    @elseif ($step === 'documents')
        <div class="journey-panel">
            <h2 class="journey-panel__title">Upload your documents</h2>
            <p class="journey-panel__text">Drop your files below or click to browse. PDF, JPG, PNG or WEBP, up to 10&nbsp;MB each. Nothing is saved until you continue.</p>

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
                                <button type="button" class="dropzone-file__remove" wire:click="removeDocument('{{ $doc->value }}')" aria-label="Remove {{ $doc->label() }}">&times;</button>
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
                                <span class="dropzone__text" wire:loading.remove wire:target="documents.{{ $doc->value }}"><strong>Drag &amp; drop</strong> or <span class="dropzone__browse">browse</span></span>
                                <span class="dropzone__hint" wire:loading.remove wire:target="documents.{{ $doc->value }}">PDF, JPG, PNG or WEBP &middot; up to 10&nbsp;MB</span>
                                <span class="dropzone__uploading" wire:loading wire:target="documents.{{ $doc->value }}">Uploading&hellip;</span>
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
                <span wire:loading.remove wire:target="submitDocuments">Continue to payment</span>
                <span wire:loading wire:target="submitDocuments">Saving&hellip;</span>
            </button>
        </div>

    @elseif ($step === 'payment')
        @php $amount = '€'.number_format($amountCents / 100, $amountCents % 100 === 0 ? 0 : 2); @endphp
        <div class="journey-panel">
            <h2 class="journey-panel__title">Pay &amp; activate</h2>

            @if (! $paymentStarted)
                {{-- Aucun paiement lance : le formulaire de paiement classique. --}}
                <p class="journey-panel__text">Your file is complete. Pay your Creator Pack subscription to activate your EU Responsible Person.</p>
                <div class="journey-amount">
                    <span class="journey-amount__value">{{ $amount }}</span>
                    <span class="journey-amount__period">per year</span>
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
                    <span wire:loading.remove wire:target="pay">Pay {{ $amount }} securely</span>
                    <span wire:loading wire:target="pay">Redirecting&hellip;</span>
                </button>
            @else
                {{-- Paiement en vol : on confirme (boucle auto), sans re-proposer "Payer" => anti double-debit.
                     La boucle interroge le prestataire ; le webhook reste la source de verite en fond. --}}
                @if (! $paymentTimedOut)
                    <div class="journey-processing" wire:init="pollPayment" wire:poll.5s="pollPayment">
                        <span class="journey-processing__spinner" aria-hidden="true"></span>
                        <p class="journey-panel__text">We're confirming your payment. Some payment methods take a moment to clear &middot; this page updates on its own, no need to pay again.</p>
                    </div>
                @else
                    <p class="journey-note">Your payment is still being confirmed. We'll email you the moment it clears &middot; you can safely close this page.</p>
                @endif
                <button type="button" class="btn btn--outline-dark btn--sm" wire:click="pay" wire:loading.attr="disabled" wire:target="pay">
                    <span wire:loading.remove wire:target="pay">Haven't finished paying? Resume</span>
                    <span wire:loading wire:target="pay">Redirecting&hellip;</span>
                </button>
            @endif
        </div>

    @elseif ($step === 'done')
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="funnel-success__title">Your Creator Pack is active.</h3>
            <p class="funnel-success__text">Your mandate is signed and your payment is confirmed. We'll issue your official EU Responsible Person address and email it to you within 24 hours.</p>
        </div>

        {{-- Espace "mon dossier" : statut + telechargement du mandat signe et des documents. --}}
        <div class="dossier">
            <div class="dossier__meta">
                <div class="dossier__row"><span class="dossier__label">Reference</span><span class="dossier__value">{{ $submission->reference }}</span></div>
                <div class="dossier__row"><span class="dossier__label">Status</span><span class="dossier__value dossier__value--active">Active</span></div>
            </div>

            <h4 class="dossier__heading">Your documents</h4>
            <ul class="dossier__files">
                @if ($mandateAvailable)
                    <li class="dossier__file">
                        <svg class="dossier__file-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span class="dossier__file-name">Signed Responsible Person mandate</span>
                        <a class="dossier__download" href="{{ route('get-started.starter.mandate', ['locale' => app()->getLocale(), 'dossier' => $submission->resume_token]) }}">Download</a>
                    </li>
                @endif
                @foreach ($dossierDocuments as $doc)
                    <li class="dossier__file">
                        <svg class="dossier__file-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span class="dossier__file-name">{{ $doc->type->label() }} <span class="dossier__file-sub">&middot; {{ $doc->original_filename }}</span></span>
                        <a class="dossier__download" href="{{ route('get-started.starter.document', ['locale' => app()->getLocale(), 'dossier' => $submission->resume_token, 'document' => $doc->id]) }}">Download</a>
                    </li>
                @endforeach
            </ul>
        </div>

        <a href="{{ route('home') }}" class="btn btn--outline-dark btn--sm">Back to home</a>

    @elseif ($step === 'cancelled')
        <div class="journey-panel">
            <h2 class="journey-panel__title">This file was cancelled</h2>
            <p class="journey-panel__text">Please get in touch if you'd like to reopen it.</p>
            <a href="{{ route('contact', ['locale' => app()->getLocale()]) }}" class="btn btn--outline-dark">Contact us</a>
        </div>
    @endif
</div>
