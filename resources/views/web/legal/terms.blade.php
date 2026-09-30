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
            <x-web.legal-document document="terms" />
        </div>
    </section>
@endsection
