@extends('layouts.web')

@section('title', __('Legal notice · Festilaw'))
@section('meta_description', __('Legal information about Festilaw: publisher, hosting and contact details.'))

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => route('home')],
        ['name' => __('Legal notice'), 'url' => route('legal-notice')],
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
            <h1 class="page-hero__title">{{ __('Legal notice') }}</h1>
        </div>
    </section>

    <section class="legal">
        <div class="legal__inner">
            <x-web.legal-document document="legal-notice" />
        </div>
    </section>
@endsection
