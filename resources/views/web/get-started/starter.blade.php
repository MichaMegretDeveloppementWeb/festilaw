@extends('layouts.web')

@section('title', 'Creator Pack · Get started · Festilaw')
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">Creator Pack</span>
            <h1 class="page-hero__title">Open your <span class="page-hero__title-em">compliance file</span></h1>
            <p class="page-hero__lead">A few details to get started. Next: sign your mandate, upload your documents, and pay securely.</p>
        </div>
    </section>

    <section class="funnel">
        <div class="funnel__inner">
            <div class="funnel__card">
                <livewire:web.funnel.starter-form />
            </div>
        </div>
    </section>
@endsection
