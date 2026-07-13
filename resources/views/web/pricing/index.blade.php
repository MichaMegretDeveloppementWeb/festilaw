@extends('layouts.web')

@section('title', 'Pricing · Simple, fair, transparent GPSR compliance')
@section('meta_description', 'Simple, fair and transparent GPSR pricing: one clear public rate with no hidden per-SKU fees. Creator, Pro and Scale plans.')

@push('styles')
    @vite('resources/css/web/pricing/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">Pricing</span>
            <h1 class="page-hero__title">One clear price, <span class="page-hero__title-em">no surprises</span></h1>
            <p class="page-hero__lead">A single public rate built for creators and growing brands. No hidden per-SKU fees, no opaque quotes.</p>
        </div>
    </section>

    @include('web.sections.pricing')
    @include('web.sections.why-festilaw')
    @include('web.sections.services')
    @include('web.sections.why-gpsr')
    @include('web.sections.trust')
    @include('web.sections.final-cta')
@endsection
