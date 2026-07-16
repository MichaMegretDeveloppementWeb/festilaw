@extends('layouts.web')

@section('title', __('Terms · Festilaw'))
@section('meta_description', __('The terms governing the use of Festilaw and our GPSR Responsible Person service.'))

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => route('home')],
        ['name' => __('Terms'), 'url' => route('terms')],
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
            <h1 class="page-hero__title">{{ __('Terms') }}</h1>
        </div>
    </section>

    <section class="legal">
        <div class="legal__inner">
            <p class="legal__draft">{{ __('This is a working draft pending final legal review by Festilaw. It will be finalised before launch.') }}</p>

            <h2>{{ __('The service') }}</h2>
            <p>{{ __('Festilaw acts as your EU Responsible Person for the General Product Safety Regulation (GPSR): we provide an official EU contact address, hold your product documentation, and liaise with the authorities on your behalf, within the plan you subscribe to.') }}</p>

            <h2>{{ __('Pricing and payment') }}</h2>
            <p>{{ __('Prices are shown on our pricing page and are stated per year. Payment is made securely through our payment provider before the service begins. The exact scope of each plan is described on the site.') }}</p>

            <h2>{{ __('Your responsibilities') }}</h2>
            <p>{{ __('You are responsible for the accuracy and completeness of the information and documents you provide, and for the safety and compliance of your own products. We rely on the documents you supply to carry out our role.') }}</p>

            <h2>{{ __('Liability') }}</h2>
            <p>{{ __('Festilaw provides its service with professional care, but does not manufacture your products and is not liable for their intrinsic safety. Nothing in these terms limits liability that cannot be limited by law.') }}</p>

            <h2>{{ __('Governing law') }}</h2>
            <p>{{ __('These terms are governed by the applicable law, which will be specified here before launch. Any dispute will be handled by the competent courts.') }}</p>
        </div>
    </section>
@endsection
