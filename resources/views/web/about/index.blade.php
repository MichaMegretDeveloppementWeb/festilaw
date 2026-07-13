@extends('layouts.web')

@section('title', 'About Festilaw · Your GPSR compliance partner')
@section('meta_description', 'Festilaw is a GPSR compliance partner for brands selling into Europe, built by entrepreneurs, for entrepreneurs.')

@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => route('home')],
        ['name' => 'About', 'url' => route('about')],
    ];
    $jsonLdNodes = [
        [
            '@type' => 'AboutPage',
            'name' => 'About Festilaw',
            'url' => route('about'),
            'description' => 'Festilaw is a GPSR compliance partner for brands selling into Europe, built by entrepreneurs, for entrepreneurs.',
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
            <span class="eyebrow page-hero__eyebrow">About</span>
            <h1 class="page-hero__title">Built by entrepreneurs, <span class="page-hero__title-em">for entrepreneurs</span></h1>
            <p class="page-hero__lead">Festilaw is a compliance partner for brands selling into Europe, run by people who understand what it takes to build a product and get it to market.</p>
        </div>
    </section>

    @include('web.sections.who-we-are')
    @include('web.sections.why-festilaw')
    @include('web.sections.trust')
    @include('web.sections.final-cta')
@endsection
