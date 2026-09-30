@extends('layouts.web')

@section('title', __('Privacy policy · Festilaw'))
@section('meta_description', __('How Festilaw collects, uses and protects your personal data, and the rights you have under the GDPR.'))

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => route('home')],
        ['name' => __('Privacy policy'), 'url' => route('privacy-policy')],
    ];
@endphp

@push('styles')
    @vite('resources/css/web/legal/index.css')
@endpush

@section('content')
    <section class="page-hero page-hero--tight">
        <div class="page-hero__inner">
            <x-web.breadcrumb :items="$breadcrumbs" />
            <span class="eyebrow page-hero__eyebrow">{{ __('Legal') }}</span>
            <h1 class="page-hero__title">{{ __('Privacy policy') }}</h1>
            <p class="page-hero__lead">{{ __('We only collect what we need to provide our service, and we protect it.') }}</p>
        </div>
    </section>

    <section class="legal">
        <div class="legal__inner">
            <x-web.legal-document document="privacy-policy" />
        </div>
    </section>
@endsection
