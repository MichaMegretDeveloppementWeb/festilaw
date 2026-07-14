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
    <section class="page-hero">
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">Creator Pack</span>
            <h1 class="page-hero__title">Your <span class="page-hero__title-em">compliance file</span></h1>
            <p class="page-hero__lead">Three steps to your EU Responsible Person: sign your mandate, upload your documents, and pay securely.</p>
        </div>
    </section>

    <section class="funnel">
        <div class="funnel__inner">
            <div class="funnel__card">
                <livewire:web.funnel.starter-journey :submission="$submission" />
            </div>
        </div>
    </section>
@endsection
