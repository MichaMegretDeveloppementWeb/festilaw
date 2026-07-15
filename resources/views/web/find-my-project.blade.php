@extends('layouts.web')

@section('title', __('Access my project · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/journey.css')
@endpush

@section('content')
    <section class="my-project">
        <div class="my-project__inner">
            <header class="my-project__head">
                <span class="eyebrow">{{ __('Your project') }}</span>
                <h1 class="my-project__title">{{ __('Access') }} <span class="my-project__title-em">{{ __('my project') }}</span></h1>
                <p class="my-project__intro">{{ __('Enter your email and we\'ll send you a secure link to your Festilaw project · no account, no password.') }}</p>
            </header>

            <div class="my-project__card">
                <livewire:web.funnel.access-file-form />
            </div>
        </div>
    </section>
@endsection
