@extends('layouts.web')

@section('title', __('Scale Pack · Get started · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/index.css')
@endpush

@section('content')
    <section class="funnel">
        <div class="funnel__inner funnel__inner--split">
            <div class="funnel__card">
                <header class="funnel__head">
                    <span class="eyebrow">{{ __('Scale Pack') }}</span>
                    <h1 class="funnel__title">{!! __('Start with a :audit', ['audit' => '<span class="funnel__title-em">'.e(__('compliance audit')).'</span>']) !!}</h1>
                    <p class="funnel__intro">{{ __('For 100+ products. Request your audit, then pay the €75 fee, deducted from your final contract, and book a consultation with our experts.') }}</p>
                </header>

                <div class="funnel__body">
                    <div class="funnel__form-col">
                        <livewire:web.funnel.scale-form />
                        <p class="funnel__reassure">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            {{ __('The €75 audit fee is fully deducted from your final contract.') }}
                        </p>
                    </div>

                    <x-funnel.summary
                        :plan="__('Scale Pack')"
                        :price="__('Custom')"
                        :scope="__('100+ products, with full audit')"
                        :included="[
                            __('A paid compliance audit (€75, deducted from your contract)'),
                            __('A consultation with our experts'),
                            __('A tailored setup for large catalogues'),
                            __('Everything in the Pro Pack'),
                        ]"
                        :note="__('We start with an audit so your setup fits your catalogue exactly.')" />
                </div>
            </div>
        </div>
    </section>
@endsection
