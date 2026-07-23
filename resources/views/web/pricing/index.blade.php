@extends('layouts.web')

@section('title', __('Pricing · Simple, fair, transparent GPSR compliance'))
@section('meta_description', __('Simple, fair and transparent GPSR pricing: one clear public rate with no hidden per-SKU fees. Creator, Pro and Scale plans.'))

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => route('home')],
        ['name' => __('Pricing'), 'url' => route('pricing')],
    ];
    $faqItems = [
        ['q' => __('How does Festilaw work?'), 'a' => __('You choose the plan that fits your size, complete a short form, sign your mandate online, upload the required documents, and pay securely. We then provide your official EU Responsible Person address and your signed mandate, typically within 24 hours.')],
        ['q' => __('How fast do I get my mandate?'), 'a' => __('Our promise is a mandate and EU Responsible Person address within 24 hours of your file being complete.')],
        ['q' => __('How much does it cost? Are there hidden per-SKU fees?'), 'a' => __('Our pricing is public and flat, with no hidden per-SKU fees: Creator Pack at :creator EUR per year (up to 9 products), Pro Pack at :pro EUR per year (10 to 100 products), and Scale Pack on request (100+ products).', ['creator' => number_format($creatorAnnualCents / 100), 'pro' => number_format($proAnnualCents / 100)])],
        ['q' => __('What do you need from me?'), 'a' => __('For the Creator plan: your company details, proof that you meet the plan\'s eligibility, and your product technical documentation or test reports.')],
        ['q' => __('Will this keep my Etsy, Amazon or Shopify shop open?'), 'a' => __('Our role is to make you compliant so your listings meet GPSR requirements and stay online. Marketplaces increasingly require a Responsible Person; we provide the official EU details you need to display.')],
        ['q' => __('Is my data safe?'), 'a' => __('Yes. We handle your data in line with the GDPR, use secure payment and secure document storage, and only collect what is needed to provide the service.')],
        ['q' => __('What if I still have questions before subscribing?'), 'a' => __('You can reach us any time through our contact page, and tell us about your situation before you commit to a plan.')],
    ];
    $jsonLdNodes = [
        [
            '@type' => 'Service',
            'serviceType' => 'GPSR Responsible Person',
            'provider' => ['@type' => 'Organization', 'name' => config('app.name')],
            'areaServed' => 'EU',
            'offers' => [
                ['@type' => 'Offer', 'name' => 'Creator Pack', 'price' => (string) intdiv($creatorAnnualCents, 100), 'priceCurrency' => 'EUR'],
                ['@type' => 'Offer', 'name' => 'Pro Pack', 'price' => (string) intdiv($proAnnualCents, 100), 'priceCurrency' => 'EUR'],
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
            <span class="eyebrow page-hero__eyebrow">{{ __('Pricing') }}</span>
            <h1 class="page-hero__title">{{ __('One clear price,') }} <span class="page-hero__title-em">{{ __('no surprises') }}</span></h1>
            <p class="page-hero__lead">{{ __('A single public rate built for creators and growing brands. No hidden per-SKU fees, no opaque quotes.') }}</p>
        </div>
    </section>

    @include('web.sections.pricing')
    @include('web.sections.why-festilaw')
    @include('web.sections.services')
    @include('web.sections.why-gpsr')
    @include('web.sections.trust')
    <x-web.faq :items="$faqItems" eyebrow="FAQ" :title="__('Pricing and process, answered')" />
    @include('web.sections.final-cta')
@endsection
