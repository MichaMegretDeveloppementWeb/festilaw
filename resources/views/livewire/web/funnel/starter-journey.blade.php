<div class="journey">
    @if (session('starter_status') === 'signed')
        <div class="journey-flash journey-flash--ok">Mandate signed. Next: upload your documents.</div>
    @elseif (session('starter_status') === 'paid')
        <div class="journey-flash journey-flash--ok">Payment received. Your file is complete.</div>
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
        </div>

    @elseif ($step === 'documents')
        <div class="journey-panel">
            <h2 class="journey-panel__title">Upload your documents</h2>
            <p class="journey-panel__text">We need the following to finalise your file. Accepted formats: PDF or image, up to 10&nbsp;MB each.</p>
            <ul class="journey-docs">
                @foreach ($requiredDocuments as $doc)
                    @php $present = in_array($doc->value, $presentTypes, true); @endphp
                    <li @class(['journey-doc', 'is-done' => $present])>
                        <div class="journey-doc__head">
                            <span class="journey-doc__name">{{ $doc->label() }}</span>
                            @if ($present)
                                <span class="journey-doc__status">Uploaded</span>
                            @endif
                        </div>
                        <p class="journey-doc__hint">{{ $doc->hint() }}</p>
                        @unless ($present)
                            <div class="journey-doc__upload">
                                <input type="file" wire:model="documents.{{ $doc->value }}" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="btn btn--outline-dark btn--sm"
                                        wire:click="uploadDocument('{{ $doc->value }}')"
                                        wire:loading.attr="disabled" wire:target="uploadDocument('{{ $doc->value }}')">
                                    <span wire:loading.remove wire:target="uploadDocument('{{ $doc->value }}')">Upload</span>
                                    <span wire:loading wire:target="uploadDocument('{{ $doc->value }}')">Uploading&hellip;</span>
                                </button>
                            </div>
                            @error("documents.{$doc->value}") <span class="funnel-form__error">{{ $message }}</span> @enderror
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>

    @elseif ($step === 'payment')
        @php $amount = '€'.number_format($amountCents / 100, $amountCents % 100 === 0 ? 0 : 2); @endphp
        <div class="journey-panel">
            <h2 class="journey-panel__title">Pay &amp; activate</h2>
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
        </div>

    @elseif ($step === 'done')
        <div class="funnel-success">
            <div class="funnel-success__icon">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="funnel-success__title">Your Creator Pack is active.</h3>
            <p class="funnel-success__text">Payment received and your mandate is signed. We'll issue your official EU Responsible Person address and email it to you within 24 hours.</p>
            <a href="{{ route('home') }}" class="btn btn--outline-dark btn--sm">Back to home</a>
        </div>

    @elseif ($step === 'cancelled')
        <div class="journey-panel">
            <h2 class="journey-panel__title">This file was cancelled</h2>
            <p class="journey-panel__text">Please get in touch if you'd like to reopen it.</p>
            <a href="{{ route('contact', ['locale' => app()->getLocale()]) }}" class="btn btn--outline-dark">Contact us</a>
        </div>
    @endif
</div>
