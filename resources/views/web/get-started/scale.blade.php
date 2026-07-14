@extends('layouts.web')

@section('title', 'Scale Pack · Get started · Festilaw')
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">Scale Pack</span>
            <h1 class="page-hero__title">Start with a <span class="page-hero__title-em">compliance audit</span></h1>
            <p class="page-hero__lead">For 100+ products. Request your audit, then pay the &euro;75 fee (deducted from your final contract) and book a consultation.</p>
        </div>
    </section>

    <section class="funnel">
        <div class="funnel__inner">
            <div class="funnel__card">
                <livewire:web.funnel.scale-form />
            </div>
        </div>
    </section>
@endsection
