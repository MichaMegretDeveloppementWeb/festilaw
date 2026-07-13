@extends('layouts.web')

@section('title', 'Pricing · Simple, fair, transparent GPSR compliance')
@section('meta_description', 'Simple, fair and transparent GPSR pricing: one clear public rate with no hidden per-SKU fees. Creator, Pro and Scale plans.')

@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => route('home')],
        ['name' => 'Pricing', 'url' => route('pricing')],
    ];
    $faqItems = [
        ['q' => 'How does Festilaw work?', 'a' => 'You choose the plan that fits your size, complete a short form, sign your mandate online, upload the required documents, and pay securely. We then provide your official EU Responsible Person address and your signed mandate, typically within 24 hours.'],
        ['q' => 'How fast do I get my mandate?', 'a' => 'Our promise is a mandate and EU Responsible Person address within 24 hours of your file being complete.'],
        ['q' => 'How much does it cost? Are there hidden per-SKU fees?', 'a' => 'Our pricing is public and flat, with no hidden per-SKU fees: Creator Pack at 333 EUR per year (up to 9 products), Pro Pack at 1,200 EUR per year (10 to 100 products), and Scale Pack on request (100+ products).'],
        ['q' => 'What do you need from me?', 'a' => "For the Creator plan: your company details, proof that you meet the plan's eligibility, and your product technical documentation or test reports."],
        ['q' => 'Will this keep my Etsy, Amazon or Shopify shop open?', 'a' => 'Our role is to make you compliant so your listings meet GPSR requirements and stay online. Marketplaces increasingly require a Responsible Person; we provide the official EU details you need to display.'],
        ['q' => 'Is my data safe?', 'a' => 'Yes. We handle your data in line with the GDPR, use secure payment and secure document storage, and only collect what is needed to provide the service.'],
        ['q' => 'What if I still have questions before subscribing?', 'a' => 'You can reach us any time through our contact page, and tell us about your situation before you commit to a plan.'],
    ];
    $jsonLdNodes = [
        [
            '@type' => 'Service',
            'serviceType' => 'GPSR Responsible Person',
            'provider' => ['@type' => 'Organization', 'name' => config('app.name')],
            'areaServed' => 'EU',
            'offers' => [
                ['@type' => 'Offer', 'name' => 'Creator Pack', 'price' => '333', 'priceCurrency' => 'EUR'],
                ['@type' => 'Offer', 'name' => 'Pro Pack', 'price' => '1200', 'priceCurrency' => 'EUR'],
            ],
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], $faqItems),
        ],
    ];
@endphp

@push('styles')
    @vite('resources/css/web/pricing/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <x-web.breadcrumb :items="$breadcrumbs" />
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
    <x-web.faq :items="$faqItems" eyebrow="FAQ" title="Pricing and process, answered" />
    @include('web.sections.final-cta')
@endsection
