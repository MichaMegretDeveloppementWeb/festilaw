@extends('layouts.web')

@section('title', 'Festilaw · Your GPSR Responsible Person')
@section('meta_description', 'Sell safely in the European market. Festilaw is your GPSR Responsible Person, with dedicated support from entrepreneurs for entrepreneurs.')

@push('styles')
    @vite('resources/css/web/home/index.css')
@endpush

@section('content')
    @include('web.home.partials.hero')
    @include('web.sections.who-we-are')
    @include('web.sections.why-festilaw')
    @include('web.sections.quiz')
    @include('web.sections.services')
    @include('web.sections.pricing')
    @include('web.sections.why-gpsr')
    @include('web.sections.trust')
    @include('web.home.partials.final-cta')
@endsection
