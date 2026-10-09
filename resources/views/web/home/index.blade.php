@extends('layouts.web')

@section('title', __('Festilaw · Your GPSR Responsible Person'))
@section('meta_description', __('Sell safely in the European market. Festilaw is your GPSR Responsible Person, with dedicated support from entrepreneurs for entrepreneurs.'))

@push('meta')
    {{-- LCP mobile : le fond CSS garde son cadrage/parallax, mais se charge des le HTML. --}}
    <link rel="preload" as="image" href="{{ asset('images/home-transport-optimized.avif') }}" type="image/avif" fetchpriority="high">
@endpush

@push('styles')
    @vite('resources/css/web/home/index.css')
@endpush

@section('content')
    @include('web.home.partials.hero')
    <div class="home-photo" role="img" aria-label="{{ __('A container ship carrying goods across the sea.') }}"></div>
    @include('web.sections.who-we-are')
    @include('web.sections.why-festilaw')
    @include('web.sections.quiz')
    @include('web.sections.services')
    @include('web.sections.pricing')
    @include('web.sections.why-gpsr')
    @include('web.sections.trust')
    @include('web.sections.final-cta')
@endsection
