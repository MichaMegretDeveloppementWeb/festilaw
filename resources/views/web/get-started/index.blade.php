@extends('layouts.web')

@section('title', 'Get started · Festilaw')
@section('meta_description', 'Choose your GPSR compliance plan: Creator, Pro or Scale, and get your EU Responsible Person set up.')
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">Get started</span>
            <h1 class="page-hero__title">Choose your <span class="page-hero__title-em">path</span></h1>
            <p class="page-hero__lead">Pick the plan that fits your catalogue. Not sure yet? Take the 30-second eligibility check on the home page or get in touch.</p>
        </div>
    </section>

    <section class="paths">
        <div class="paths__grid">
            <article class="path-card">
                <h2 class="path-card__name">Creator Pack</h2>
                <div class="path-card__price">&euro;333</div>
                <div class="path-card__period">per year &middot; up to 9 products</div>
                <p class="path-card__desc">Sign your mandate online, upload your documents, and get your official EU Responsible Person address within 24 hours.</p>
                <a href="{{ route('get-started.starter') }}" class="btn btn--outline-dark path-card__cta">Choose Creator</a>
            </article>

            <article class="path-card path-card--featured">
                <span class="path-card__badge">Most popular</span>
                <h2 class="path-card__name">Pro Pack</h2>
                <div class="path-card__price">&euro;1,200</div>
                <div class="path-card__period">per year &middot; 10 to 100 products</div>
                <p class="path-card__desc">A dedicated setup for growing brands. Tell us about your catalogue and we'll guide you personally.</p>
                <a href="{{ route('get-started.pro') }}" class="btn btn--coral path-card__cta">Choose Pro</a>
            </article>

            <article class="path-card">
                <h2 class="path-card__name">Scale Pack</h2>
                <div class="path-card__price">Custom</div>
                <div class="path-card__period">100+ products &middot; with full audit</div>
                <p class="path-card__desc">Start with a paid compliance audit (deducted from your final contract), then book a consultation with our experts.</p>
                <a href="{{ route('get-started.scale') }}" class="btn btn--outline-dark path-card__cta">Start with an audit</a>
            </article>
        </div>
    </section>
@endsection
