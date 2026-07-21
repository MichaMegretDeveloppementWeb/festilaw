@extends('layouts.web')

@section('title', __('Creator Pack · Get started · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/index.css')
@endpush

@section('content')
    <section class="funnel">
        <div class="funnel__inner funnel__inner--split">
            <div class="funnel__card">
                <header class="funnel__head">
                    <span class="eyebrow">{{ __('Creator Pack') }}</span>
                    <h1 class="funnel__title">{!! __('Open your :file', ['file' => '<span class="funnel__title-em">'.e(__('compliance file')).'</span>']) !!}</h1>
                    <p class="funnel__intro">{{ __('A few details to get started. Next, you\'ll sign your mandate, upload your documents, and pay securely. It takes about two minutes.') }}</p>
                </header>

                <div class="funnel__body">
                    <div class="funnel__form-col">
                        <livewire:web.funnel.starter-form :type="'starter'" />
                        <p class="funnel__reassure">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            {{ __('No payment at this step. You\'ll review everything before you pay.') }}
                        </p>
                    </div>

                    <x-funnel.summary
                        :plan="__('Creator Pack')"
                        :price="__('€333 / year')"
                        :scope="__('up to 9 products')"
                        :included="[
                            __('Your official EU Responsible Person address'),
                            __('Set up and live within 24 hours'),
                            __('Sign your mandate 100% online'),
                            __('Real human support, from entrepreneurs'),
                        ]"
                        :note="__('Cancel anytime before payment. Your details are never shared with third parties.')" />
                </div>
            </div>
        </div>
    </section>
@endsection
