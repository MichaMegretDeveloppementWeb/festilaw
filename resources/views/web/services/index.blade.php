@extends('layouts.web')

@section('title', __('Our Services · Festilaw GPSR compliance'))
@section('meta_description', __('From compliance assessment to authority liaison and regulatory watch, Festilaw handles your full GPSR compliance as your EU Responsible Person.'))

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => route('home')],
        ['name' => __('Our Services'), 'url' => route('services')],
    ];
    $jsonLdNodes = [
        [
            '@type' => 'Service',
            'serviceType' => 'GPSR Responsible Person',
            'name' => __('GPSR compliance services'),
            'provider' => ['@type' => 'Organization', 'name' => config('app.name')],
            'areaServed' => 'EU',
            'url' => route('services'),
        ],
    ];
@endphp

@push('styles')
    @vite('resources/css/web/services/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <x-web.breadcrumb :items="$breadcrumbs" />
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">{{ __('Our Services') }}</span>
            <h1 class="page-hero__title">{{ __('Your full GPSR service,') }} <span class="page-hero__title-em">{{ __('handled') }}</span></h1>
            <p class="page-hero__lead">{{ __('One partner for legal representation, compliance assessment, documentation, authority liaison and ongoing monitoring, so you can sell into Europe with confidence.') }}</p>
        </div>
    </section>

    @include('web.sections.services')
    @include('web.sections.final-cta')
@endsection
