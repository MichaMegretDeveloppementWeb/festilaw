@extends('layouts.web')

@section('title', __('Your file · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('meta')
    {{-- Le token du dossier est dans l'URL : on evite de le divulguer via le Referer aux tiers. --}}
    <meta name="referrer" content="same-origin">
@endpush

@push('styles')
    @vite('resources/css/web/get-started/journey.css')
@endpush

@section('content')
    <section class="my-file">
        <div class="my-file__inner">
            <header class="my-file__head">
                <span class="eyebrow">{{ __('Your file') }}</span>
                <h1 class="my-file__title">{!! __('Your :pack', ['pack' => '<span class="my-file__title-em">'.e(__('Creator Pack')).'</span>']) !!}</h1>
                <p class="my-file__intro">{{ __('Everything about your EU Responsible Person, in one place. Keep this link private · it\'s your secure access, no account needed.') }}</p>
            </header>

            <div class="my-file__card">
                <span class="my-file__badge">{{ __('Active') }}</span>

                <dl class="my-file__meta">
                    <div class="my-file__meta-row">
                        <dt>{{ __('Reference') }}</dt>
                        <dd>{{ $submission->reference }}</dd>
                    </div>
                    <div class="my-file__meta-row">
                        <dt>{{ __('Plan') }}</dt>
                        <dd>{{ __('Creator Pack · €333 / year') }}</dd>
                    </div>
                    @if ($renewsAt)
                        <div class="my-file__meta-row">
                            <dt>{{ __('Next renewal') }}</dt>
                            <dd>{{ $renewsAt->isoFormat('D MMMM YYYY') }}</dd>
                        </div>
                    @endif
                    <div class="my-file__meta-row">
                        <dt>{{ __('EU Responsible Person') }}</dt>
                        <dd class="my-file__pending">{{ __('Issued within 24 h, emailed to you') }}</dd>
                    </div>
                </dl>

                <h2 class="my-file__heading">{{ __('Your documents') }}</h2>
                <ul class="dossier__files">
                    @if ($submission->contract?->signed_file_path)
                        <li class="dossier__file">
                            <svg class="dossier__file-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span class="dossier__file-name">{{ __('Signed Responsible Person mandate') }}</span>
                            <a class="dossier__download" href="{{ route('get-started.starter.mandate', ['locale' => app()->getLocale(), 'dossier' => $submission->resume_token]) }}">{{ __('Download') }}</a>
                        </li>
                    @endif
                    @foreach ($submission->uploadedDocuments as $doc)
                        <li class="dossier__file">
                            <svg class="dossier__file-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span class="dossier__file-name">{{ $doc->type->label() }} <span class="dossier__file-sub">&middot; {{ $doc->original_filename }}</span></span>
                            <a class="dossier__download" href="{{ route('get-started.starter.document', ['locale' => app()->getLocale(), 'dossier' => $submission->resume_token, 'document' => $doc->id]) }}">{{ __('Download') }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="my-file__support">{{ __('Need anything?') }} <a href="{{ route('contact', ['locale' => app()->getLocale()]) }}">{{ __('Contact us') }}</a> {{ __('· real humans, happy to help.') }}</p>
        </div>
    </section>
@endsection
