@extends('layouts.web')

@section('title', __('Access my file · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/journey.css')
@endpush

@section('content')
    <section class="my-file">
        <div class="my-file__inner">
            <header class="my-file__head">
                <span class="eyebrow">{{ __('Your file') }}</span>
                <h1 class="my-file__title">{!! __('Access :file', ['file' => '<span class="my-file__title-em">'.e(__('my file')).'</span>']) !!}</h1>
                <p class="my-file__intro">{{ __('Enter your email and we\'ll send you a secure link to your Festilaw file · no account, no password.') }}</p>
            </header>

            <div class="my-file__card">
                <livewire:web.funnel.access-file-form />
            </div>
        </div>
    </section>
@endsection
