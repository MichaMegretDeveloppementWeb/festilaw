@extends('layouts.web')

@section('title', __('Your Scale audit · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('meta')
    {{-- Le token du dossier est dans l'URL : on evite de le divulguer via le Referer aux tiers. --}}
    <meta name="referrer" content="same-origin">
@endpush

@push('styles')
    @vite('resources/css/web/get-started/journey.css')
@endpush

@section('content')
    @php
        $auditPrice = '€'.number_format($space->auditAmountCents / 100, $space->auditAmountCents % 100 === 0 ? 0 : 2);
    @endphp
    <section class="my-project">
        <div class="my-project__inner">
            <header class="my-project__head">
                <span class="eyebrow">{{ __('Scale Pack') }}</span>
                <h1 class="my-project__title">{{ __('Your') }} <span class="my-project__title-em">{{ __('expert audit') }}</span></h1>
                <p class="my-project__intro">{{ __('Pay your audit, then book your video consultation. Keep this link private · it\'s your secure access, no account needed.') }}</p>
            </header>

            <div class="my-project__card">
                <div class="my-project__statusbar">
                    @php
                        $badgeLabel = $space->cancelled ? __('Cancelled')
                            : (! $space->auditPaid ? __('Audit to pay')
                            : (! $space->booked ? __('Consultation to book')
                            : __('Consultation requested')));
                    @endphp
                    <span @class([
                        'my-project__badge',
                        'is-active' => $space->auditPaid && $space->booked,
                        'is-warn' => $space->auditPaid && ! $space->booked,
                        'is-cancelled' => $space->cancelled,
                        'is-progress' => ! $space->auditPaid && ! $space->cancelled,
                    ])>{{ $badgeLabel }}</span>
                    <span class="my-project__ref">{{ __('Ref.') }} {{ $space->reference }}</span>
                </div>

                @if ($space->cancelled)
                    <p class="my-project__note">{{ __('This project was cancelled. Get in touch if you\'d like to reopen it.') }}</p>
                    <a href="{{ route('contact') }}" class="btn btn--outline-dark btn--sm">{{ __('Contact us') }}</a>
                @else
                    @if (session('scale_booked'))
                        <div class="scale-flash scale-flash--ok">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            {{ __('Thanks · your booking request is recorded.') }}
                        </div>
                    @endif
                    @if (session('scale_error'))
                        <p class="my-project__renew-error">{{ session('scale_error') }}</p>
                    @endif

                    <ul class="project-steps">
                        <li @class(['project-step', 'is-done' => $space->auditPaid])>
                            <span class="project-step__mark">
                                @if ($space->auditPaid)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                @endif
                            </span>
                            <span class="project-step__label">{{ __('Audit paid') }}@if ($space->auditPaid && $space->paidAt) <span class="project-step__amount">({{ $auditPrice }} &middot; {{ $space->paidAt->isoFormat('D MMMM YYYY') }})</span>@endif</span>
                        </li>
                        <li @class(['project-step', 'is-done' => $space->booked])>
                            <span class="project-step__mark">
                                @if ($space->booked)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                @endif
                            </span>
                            <span class="project-step__label">{{ __('Consultation booked') }}</span>
                        </li>
                    </ul>

                    @if (! $space->auditPaid)
                        <div class="scale-action">
                            <p class="scale-action__lead">{{ __('Pay your expert audit to unlock your consultation booking.') }}</p>
                            <div class="scale-price">
                                <span class="scale-price__value">{{ $auditPrice }}</span>
                                <span class="scale-price__note">{{ __('one-off · credited toward your final quote') }}</span>
                            </div>
                            <form method="POST" action="{{ $space->payUrl }}">
                                @csrf
                                <button type="submit" class="btn btn--coral">{{ __('Pay :amount audit', ['amount' => $auditPrice]) }}</button>
                            </form>
                        </div>
                    @elseif (! $space->booked)
                        <div class="scale-action">
                            <p class="scale-action__lead">{{ __('Your audit is paid. Two quick steps to lock in your video consultation:') }}</p>
                            <ol class="scale-book">
                                <li class="scale-book__step">
                                    <span class="scale-book__num">1</span>
                                    <div class="scale-book__body">
                                        <p class="scale-book__title">{{ __('Pick a time that suits you') }}</p>
                                        <p class="scale-book__hint">{{ __('Opens our booking calendar · times shown in Paris time.') }}</p>
                                        <a href="{{ $space->calendarUrl }}" target="_blank" rel="noopener" class="btn btn--coral btn--sm" data-scale-open>
                                            {{ __('Open the booking calendar') }}
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                        </a>
                                    </div>
                                </li>
                                <li class="scale-book__step scale-book__step--confirm" data-scale-confirm>
                                    <span class="scale-book__num">2</span>
                                    <div class="scale-book__body">
                                        <p class="scale-book__title">{{ __('Confirm your booking') }}</p>
                                        <p class="scale-book__hint">{{ __('Once you\'ve picked a slot, let us know so we can lock it in.') }}</p>
                                        <form method="POST" action="{{ $space->bookUrl }}">
                                            @csrf
                                            <button type="submit" class="btn btn--outline-dark btn--sm" data-scale-confirm-btn>{{ __('I\'ve booked my consultation') }}</button>
                                        </form>
                                        <p class="scale-book__locked" data-scale-locked>{{ __('Open the calendar in step 1 first.') }}</p>
                                    </div>
                                </li>
                            </ol>
                            <p class="scale-action__credit">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                {{ __('Your :price audit fee will be credited toward your final quote.', ['price' => $auditPrice]) }}
                            </p>
                        </div>
                    @else
                        <div class="scale-action">
                            <dl class="my-project__meta">
                                <div class="my-project__meta-row">
                                    <dt>{{ __('Consultation') }}</dt>
                                    <dd>{{ $space->appointmentStatusLabel }}@if ($space->scheduledAt) &middot; {{ $space->scheduledAt->isoFormat('D MMMM YYYY · HH:mm') }}@endif</dd>
                                </div>
                            </dl>
                            <p class="my-project__note">{{ __('Thanks · your consultation request is recorded. Our team will confirm the exact slot by email. Your :price audit fee will be credited toward your final quote.', ['price' => $auditPrice]) }}</p>
                            <p class="scale-book__reopen">{{ __('Haven\'t picked a slot yet?') }} <a href="{{ $space->calendarUrl }}" target="_blank" rel="noopener">{{ __('Open the booking calendar') }}</a></p>
                        </div>
                    @endif
                @endif
            </div>

            <p class="my-project__support">{{ __('Need anything?') }} <a href="{{ route('contact') }}">{{ __('Contact us') }}</a> · {{ __('real humans, happy to help.') }}</p>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        // Progressive : surligne l'etape 2 (confirmer) une fois l'agenda ouvert. Sans JS, les deux etapes
        // restent visibles et pleinement fonctionnelles.
        (function () {
            var open = document.querySelector('[data-scale-open]');
            var step = document.querySelector('[data-scale-confirm]');
            var btn = document.querySelector('[data-scale-confirm-btn]');
            var locked = document.querySelector('[data-scale-locked]');
            if (!open || !step || !btn) { return; }
            // Sans JS, le bouton reste actif (repli). Avec JS, il est verrouille tant que l'agenda n'a pas
            // ete ouvert : on evite un "J'ai reserve" clique par erreur avant meme d'avoir vu l'agenda.
            btn.disabled = true;
            if (locked) { locked.classList.add('is-shown'); }
            open.addEventListener('click', function () {
                step.classList.add('is-ready');
                btn.disabled = false;
                if (locked) { locked.classList.remove('is-shown'); }
            });
        })();
    </script>
@endpush
