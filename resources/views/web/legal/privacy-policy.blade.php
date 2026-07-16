@extends('layouts.web')

@section('title', __('Privacy policy · Festilaw'))
@section('meta_description', __('How Festilaw collects, uses and protects your personal data, and the rights you have under the GDPR.'))

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => route('home')],
        ['name' => __('Privacy policy'), 'url' => route('privacy-policy')],
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
            <h1 class="page-hero__title">{{ __('Privacy policy') }}</h1>
            <p class="page-hero__lead">{{ __('We only collect what we need to provide our service, and we protect it.') }}</p>
        </div>
    </section>

    <section class="legal">
        <div class="legal__inner">
            <p class="legal__draft">{{ __('This is a working draft pending final legal review by Festilaw. It describes our intended practices and will be finalised before launch.') }}</p>

            <h2>{{ __('Who is responsible for your data') }}</h2>
            <p>{{ __('Festilaw is the data controller for the personal data collected through this website. For any question about this policy or your data, contact us at :email.', ['email' => config('festilaw.notification_email')]) }}</p>

            <h2>{{ __('What we collect') }}</h2>
            <ul>
                <li>{{ __('Contact details you provide (name, email, company, website).') }}</li>
                <li>{{ __('Information needed to set up your EU Responsible Person (company details, and the documents you upload, such as proof of turnover and product technical documentation).') }}</li>
                <li>{{ __('Payment status (payments are processed by our payment provider; we never see your full card details).') }}</li>
                <li>{{ __('Technical data strictly necessary to run the site (a session cookie). We do not use advertising or tracking cookies.') }}</li>
            </ul>

            <h2>{{ __('Why we collect it and on what basis') }}</h2>
            <p>{{ __('We use your data to provide the service you request (performing our contract with you), to reply to your messages, and to meet our legal obligations. We do not sell your data.') }}</p>

            <h2>{{ __('Who we share it with') }}</h2>
            <p>{{ __('We share data only with the providers strictly needed to deliver the service: our electronic signature provider, our payment provider, and our email provider. They act on our instructions and under their own security and privacy commitments.') }}</p>

            <h2>{{ __('How long we keep it') }}</h2>
            <p>{{ __('We keep your data only as long as needed to provide the service and to meet our legal and accounting obligations, then we delete or anonymise it. Uploaded documents are stored on a private, access-controlled storage.') }}</p>

            <h2>{{ __('Your rights') }}</h2>
            <p>{{ __('Under the GDPR you can access, correct, delete, or export your data, and object to or restrict its processing. To exercise these rights, contact us at :email. You may also lodge a complaint with your national data protection authority.', ['email' => config('festilaw.notification_email')]) }}</p>
        </div>
    </section>
@endsection
