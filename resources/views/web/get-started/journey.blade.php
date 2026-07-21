@extends('layouts.web')

@php
    $isPro = $submission->type->value === 'pro';
    $packLabel = $submission->type->label();
@endphp

@section('title', __('Your :pack · Get started · Festilaw', ['pack' => __($packLabel)]))
@section('robots', 'noindex, nofollow')

@push('meta')
    {{-- Le token du dossier est dans l'URL : on evite de le divulguer via le Referer aux tiers. --}}
    <meta name="referrer" content="same-origin">
@endpush

@push('styles')
    @vite('resources/css/web/get-started/journey.css')
@endpush

@section('content')
    <section class="funnel">
        <div class="funnel__inner funnel__inner--split">
            <div class="funnel__card">
                <header class="funnel__head">
                    <span class="eyebrow">{{ __($packLabel) }}</span>
                    <h1 class="funnel__title">{!! __('Your :file', ['file' => '<span class="funnel__title-em">'.e(__('compliance file')).'</span>']) !!}</h1>
                    <p class="funnel__intro">{{ __('Three steps to your EU Responsible Person: sign your mandate, upload your documents, and pay securely.') }}</p>
                </header>

                <div class="funnel__body">
                    <div class="funnel__form-col">
                        <livewire:web.funnel.starter-journey :submission="$submission" />
                    </div>

                    <x-funnel.summary
                        :plan="__($packLabel)"
                        :price="$isPro ? __('€1,200 / year') : __('€333 / year')"
                        :scope="$isPro ? __('10 to 100 products') : __('up to 9 products')"
                        :included="$isPro
                            ? [
                                __('Everything in the Creator Pack'),
                                __('Your official EU Responsible Person address'),
                                __('Priority support'),
                                __('Real human support, from entrepreneurs'),
                            ]
                            : [
                                __('Your official EU Responsible Person address'),
                                __('Set up and live within 24 hours'),
                                __('Sign your mandate 100% online'),
                                __('Real human support, from entrepreneurs'),
                            ]"
                        :note="__('Your documents are stored privately and never shared with third parties.')" />
                </div>
            </div>
        </div>
    </section>
@endsection
