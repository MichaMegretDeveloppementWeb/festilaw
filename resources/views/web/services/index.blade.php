@extends('layouts.web')

@section('title', 'Our Services · Festilaw GPSR compliance')
@section('meta_description', 'From compliance assessment to authority liaison and regulatory watch, Festilaw handles your full GPSR compliance as your EU Responsible Person.')

@push('styles')
    @vite('resources/css/web/services/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">Our Services</span>
            <h1 class="page-hero__title">Your full GPSR service, <span class="page-hero__title-em">handled</span></h1>
            <p class="page-hero__lead">One partner for legal representation, compliance assessment, documentation, authority liaison and ongoing monitoring, so you can sell into Europe with confidence.</p>
        </div>
    </section>

    @include('web.sections.services')
    @include('web.sections.final-cta')
@endsection
