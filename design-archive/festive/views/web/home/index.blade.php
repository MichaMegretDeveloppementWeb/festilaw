@extends('layouts.web')

@section('title', 'Festilaw · Your GPSR Responsible Person')
@section('meta_description', 'Sell safely in the European market. Festilaw is your GPSR Responsible Person, with dedicated support from entrepreneurs for entrepreneurs.')

@push('styles')
    @vite('resources/css/web/home/index.css')
@endpush

@section('content')
    {{-- Filtre SVG "hand-drawn" (defs cachees) utilise par les icones des piliers. --}}
    <svg class="svg-defs" aria-hidden="true"><defs><filter id="handdrawn"><feTurbulence type="fractalNoise" baseFrequency="0.018" numOctaves="2" seed="7" result="noise"/><feDisplacementMap in="SourceGraphic" in2="noise" scale="1.5"/></filter></defs></svg>

    @include('web.home.partials.hero')
    @include('web.home.partials.who-we-are')
    @include('web.home.partials.why-gpsr')
    @include('web.home.partials.why-festilaw')
    @include('web.home.partials.services')
    @include('web.home.partials.quiz')
    @include('web.home.partials.pricing')
    @include('web.home.partials.trust')
    @include('web.home.partials.final-cta')
@endsection
