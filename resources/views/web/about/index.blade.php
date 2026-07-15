@extends('layouts.web')

@section('title', __('About Festilaw · Your GPSR compliance partner'))
@section('meta_description', __('Festilaw is a GPSR compliance partner for brands selling into Europe, built by entrepreneurs, for entrepreneurs.'))

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => route('home')],
        ['name' => __('About'), 'url' => route('about')],
    ];
    $jsonLdNodes = [
        [
            '@type' => 'AboutPage',
            'name' => __('About Festilaw'),
            'url' => route('about'),
            'description' => __('Festilaw is a GPSR compliance partner for brands selling into Europe, built by entrepreneurs, for entrepreneurs.'),
        ],
    ];
@endphp

@push('styles')
    @vite('resources/css/web/about/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <x-web.breadcrumb :items="$breadcrumbs" />
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">{{ __('About') }}</span>
            <h1 class="page-hero__title">{{ __('Built by entrepreneurs,') }} <span class="page-hero__title-em">{{ __('for entrepreneurs') }}</span></h1>
            <p class="page-hero__lead">{{ __('Festilaw is a compliance partner for brands selling into Europe, run by people who understand what it takes to build a product and get it to market.') }}</p>
        </div>
    </section>

    @include('web.sections.who-we-are')
    @include('web.sections.why-festilaw')
    @include('web.sections.trust')
    @include('web.sections.final-cta')
@endsection
