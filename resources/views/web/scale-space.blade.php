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
                <p class="my-project__intro">{{ __('Pay your audit and book your 45-minute consultation. Keep this link private · it\'s your secure access, no account needed.') }}</p>
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
                        <p class="my-project__resume-text">{{ __('Pay your :price expert audit to unlock your consultation booking.', ['price' => $auditPrice]) }}</p>
                        <form method="POST" action="{{ $space->payUrl }}">
                            @csrf
                            <button type="submit" class="btn btn--coral">{{ __('Pay :amount audit', ['amount' => $auditPrice]) }}</button>
                        </form>
                        <p class="my-project__note">{{ __('Your :price audit fee will be credited toward your final quote.', ['price' => $auditPrice]) }}</p>
                    @elseif (! $space->booked)
                        <p class="my-project__resume-text">{{ __('Your audit is paid. Book your 45-minute video consultation with our expert:') }}</p>
                        <a href="{{ $space->calendarUrl }}" target="_blank" rel="noopener" class="btn btn--coral">{{ __('Open the booking calendar') }}</a>
                        <p class="my-project__note">{{ __('Once you have picked a slot in the calendar, let us know so we can confirm it.') }}</p>
                        <form method="POST" action="{{ $space->bookUrl }}">
                            @csrf
                            <button type="submit" class="btn btn--outline-dark">{{ __('I\'ve booked my consultation') }}</button>
                        </form>
                        <p class="my-project__note">{{ __('Your :price audit fee will be credited toward your final quote.', ['price' => $auditPrice]) }}</p>
                    @else
                        <dl class="my-project__meta">
                            <div class="my-project__meta-row">
                                <dt>{{ __('Consultation') }}</dt>
                                <dd>{{ $space->appointmentStatusLabel }}@if ($space->scheduledAt) &middot; {{ $space->scheduledAt->isoFormat('D MMMM YYYY · HH:mm') }}@endif</dd>
                            </div>
                        </dl>
                        <p class="my-project__note">{{ __('Thanks · your consultation request is recorded. Our team will confirm the exact slot by email. Your :price audit fee will be credited toward your final quote.', ['price' => $auditPrice]) }}</p>
                    @endif
                @endif
            </div>

            <p class="my-project__support">{{ __('Need anything?') }} <a href="{{ route('contact') }}">{{ __('Contact us') }}</a> · {{ __('real humans, happy to help.') }}</p>
        </div>
    </section>
@endsection
