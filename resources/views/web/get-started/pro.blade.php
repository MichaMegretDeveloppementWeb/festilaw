@extends('layouts.web')

@section('title', 'Pro Pack · Get started · Festilaw')
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/index.css')
@endpush

@section('content')
    <section class="funnel">
        <div class="funnel__inner funnel__inner--split">
            <div class="funnel__card">
                <header class="funnel__head">
                    <span class="eyebrow">Pro Pack</span>
                    <h1 class="funnel__title">Let's set up <span class="funnel__title-em">your compliance</span></h1>
                    <p class="funnel__intro">Tell us about your catalogue and we'll continue with you personally to tailor your Pro Pack. No commitment at this stage.</p>
                </header>

                <div class="funnel__body">
                    <div class="funnel__form-col">
                        <livewire:web.funnel.pro-form />
                        <p class="funnel__reassure">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            We'll reach out personally, usually within one business day.
                        </p>
                    </div>

                    <x-funnel.summary
                        plan="Pro Pack"
                        price="€1,200 / year"
                        scope="10 to 100 products"
                        :included="[
                            'Everything in the Creator Pack',
                            'A dedicated setup for your catalogue',
                            'Personal guidance from our team',
                            'Priority support',
                        ]"
                        note="A guided setup, not a self-checkout. We continue with you one to one." />
                </div>
            </div>
        </div>
    </section>
@endsection
