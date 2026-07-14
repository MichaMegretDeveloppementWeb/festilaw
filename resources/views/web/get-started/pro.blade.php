@extends('layouts.web')

@section('title', 'Pro Pack · Get started · Festilaw')
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">Pro Pack</span>
            <h1 class="page-hero__title">Let's set up <span class="page-hero__title-em">your compliance</span></h1>
            <p class="page-hero__lead">Tell us about your catalogue and we'll continue with you personally to tailor your Pro Pack.</p>
        </div>
    </section>

    <section class="funnel">
        <div class="funnel__inner">
            <div class="funnel__card">
                <livewire:web.funnel.pro-form />
            </div>
        </div>
    </section>
@endsection
