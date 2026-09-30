@extends('layouts.web')

{{-- Lien de dossier qui ne resout plus (remplace par un plus recent, expire ou inconnu) : on explique et on
     propose d'en recevoir un nouveau (formulaire d'acces, message generique qui ne revele rien). --}}
@section('title', __('Link no longer valid · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/journey.css')
@endpush

@section('content')
    <section class="my-project">
        <div class="my-project__inner">
            <header class="my-project__head">
                <span class="eyebrow">{{ __('Your project') }}</span>
                <h1 class="my-project__title">{{ __('This link is no longer valid') }}</h1>
                <p class="my-project__intro">{{ __('For your security, a link stops working as soon as a newer one is sent to you, or after a while if your project was not completed. Enter your email and we\'ll send you a fresh link.') }}</p>
            </header>

            <div class="my-project__card">
                <livewire:web.funnel.access-file-form />
            </div>

            <p class="my-project__support">{!! __('Requested a Scale audit? :contact and we\'ll send you a new link.', ['contact' => '<a href="'.route('contact').'">'.e(__('Contact us')).'</a>']) !!}</p>
        </div>
    </section>
@endsection
