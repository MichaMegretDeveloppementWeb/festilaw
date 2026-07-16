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
            <p class="legal__draft">{{ __('This is a working draft pending final legal review by Festilaw. It will be finalised before launch.') }}</p>

            <h2>{{ __('Publisher') }}</h2>
            <p>{{ __('This website is published by Festilaw. The full company name, legal form, registered address and registration number will be added here before launch.') }}</p>
            <p>{{ __('Contact: :email', ['email' => config('festilaw.notification_email')]) }}</p>

            <h2>{{ __('Hosting') }}</h2>
            <p>{{ __('This website is hosted by our hosting provider. The host name and address will be added here before launch.') }}</p>

            <h2>{{ __('Intellectual property') }}</h2>
            <p>{{ __('The content, brand and design of this website belong to Festilaw and may not be reused without permission.') }}</p>
        </div>
    </section>
@endsection
