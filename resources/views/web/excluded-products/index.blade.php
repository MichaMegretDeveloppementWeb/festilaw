@extends('layouts.web')

@section('title', __('Excluded products · What Festilaw does not cover'))
@section('meta_description', __('Some product categories fall under separate EU frameworks and are outside Festilaw scope: cosmetics, food, tobacco, medical devices, chemicals and more.'))

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => route('home')],
        ['name' => __('Excluded products'), 'url' => route('excluded-products')],
    ];
    $jsonLdNodes = [
        [
            '@type' => 'WebPage',
            'name' => __('Excluded products'),
            'url' => route('excluded-products'),
            'description' => __('Product categories outside Festilaw scope, governed by separate EU frameworks.'),
        ],
    ];
@endphp

@push('styles')
    @vite('resources/css/web/excluded-products/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <x-web.breadcrumb :items="$breadcrumbs" />
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">{{ __('Excluded products') }}</span>
            <h1 class="page-hero__title">{{ __('Products we') }} <span class="page-hero__title-em">{{ __('don\'t cover') }}</span></h1>
            <p class="page-hero__lead">{{ __('Festilaw focuses on standard non-food consumer products. Some categories are governed by separate European frameworks and need specialized certifications we don\'t handle.') }}</p>
        </div>
    </section>

    <section class="excluded">
        <div class="excluded__inner">
            <p class="excluded__intro">{{ __('If your products fall into one of the categories below, we\'re not the right fit. When in doubt, get in touch and we\'ll tell you honestly whether the GPSR, and Festilaw, applies to you.') }}</p>

            <div class="excluded__grid">
                <div class="excluded__item">
                    <h3 class="excluded__item-title">{{ __('Cosmetics') }}</h3>
                    <p class="excluded__item-text">{{ __('Regulated under the EU Cosmetics Regulation, with its own safety and labelling requirements.') }}</p>
                </div>
                <div class="excluded__item">
                    <h3 class="excluded__item-title">{{ __('Food & drinks') }}</h3>
                    <p class="excluded__item-text">{{ __('Covered by dedicated EU food safety law, outside the scope of the GPSR.') }}</p>
                </div>
                <div class="excluded__item">
                    <h3 class="excluded__item-title">{{ __('Tobacco') }}</h3>
                    <p class="excluded__item-text">{{ __('Subject to specific EU tobacco product directives.') }}</p>
                </div>
                <div class="excluded__item">
                    <h3 class="excluded__item-title">{{ __('Medical devices & in vitro diagnostics') }}</h3>
                    <p class="excluded__item-text">{{ __('Require strict medical certification under the MDR and IVDR frameworks.') }}</p>
                </div>
                <div class="excluded__item">
                    <h3 class="excluded__item-title">{{ __('Hazardous & industrial chemicals') }}</h3>
                    <p class="excluded__item-text">{{ __('Bulk substances, industrial mixtures, pesticides or raw materials fall under separate frameworks such as REACH.') }}</p>
                </div>
                <div class="excluded__item">
                    <h3 class="excluded__item-title">{{ __('Aviation, aerospace & military equipment') }}</h3>
                    <p class="excluded__item-text">{{ __('Governed by strict defense and aerospace safety standards.') }}</p>
                </div>
                <div class="excluded__item">
                    <h3 class="excluded__item-title">{{ __('Pharmaceuticals & human medicines') }}</h3>
                    <p class="excluded__item-text">{{ __('Require European Medicines Agency (EMA) approval before entering the market.') }}</p>
                </div>
            </div>

            <p class="excluded__note">{{ __('Not sure whether your products are covered?') }} <a href="{{ route('contact') }}" class="excluded__link">{{ __('Send us an email') }}</a> {{ __('and we\'ll point you in the right direction.') }}</p>
        </div>
    </section>

    @include('web.sections.final-cta')
@endsection
