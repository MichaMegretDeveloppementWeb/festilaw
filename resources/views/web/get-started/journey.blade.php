@extends('layouts.web')

@section('title', 'Your Creator Pack · Get started · Festilaw')
@section('robots', 'noindex, nofollow')

@push('meta')
    {{-- Le token du dossier est dans l'URL : on evite de le divulguer via le Referer aux tiers. --}}
    <meta name="referrer" content="same-origin">
@endpush

@push('styles')
    @vite('resources/css/web/get-started/journey.css')
@endpush

@section('content')
    <section class="funnel">
        <div class="funnel__inner funnel__inner--split">
            <div class="funnel__card">
                <header class="funnel__head">
                    <span class="eyebrow">Creator Pack</span>
                    <h1 class="funnel__title">Your <span class="funnel__title-em">compliance file</span></h1>
                    <p class="funnel__intro">Three steps to your EU Responsible Person: sign your mandate, upload your documents, and pay securely.</p>
                </header>

                <div class="funnel__body">
                    <div class="funnel__form-col">
                        <livewire:web.funnel.starter-journey :submission="$submission" />
                    </div>

                    <x-funnel.summary
                        plan="Creator Pack"
                        price="€333 / year"
                        scope="up to 9 products"
                        :included="[
                            'Your official EU Responsible Person address',
                            'Set up and live within 24 hours',
                            'Sign your mandate 100% online',
                            'Real human support, from entrepreneurs',
                        ]"
                        note="Your documents are stored privately and never shared with third parties." />
                </div>
            </div>
        </div>
    </section>
@endsection
