@extends('layouts.web')

@section('title', __('Your project · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('meta')
    {{-- Le token du dossier est dans l'URL : on evite de le divulguer via le Referer aux tiers. --}}
    <meta name="referrer" content="same-origin">
@endpush

@push('styles')
    @vite('resources/css/web/get-started/journey.css')
@endpush

@section('content')
    <section class="my-project">
        <div class="my-project__inner">
            <header class="my-project__head">
                <span class="eyebrow">{{ __('Your project') }}</span>
                <h1 class="my-project__title">{{ __('Your') }} <span class="my-project__title-em">{{ __('Creator Pack') }}</span></h1>
                <p class="my-project__intro">{{ __('Your compliance project in one place. Keep this link private · it\'s your secure access, no account needed.') }}</p>
            </header>

            <div class="my-project__card">
                <div class="my-project__statusbar">
                    <span @class([
                        'my-project__badge',
                        'is-active' => $paid,
                        'is-cancelled' => $cancelled,
                        'is-progress' => ! $paid && ! $cancelled,
                    ])>{{ $paid ? __('Active') : ($cancelled ? __('Cancelled') : __('In progress')) }}</span>
                    <span class="my-project__ref">{{ __('Ref.') }} {{ $submission->reference }}</span>
                </div>

                @if ($cancelled)
                    <p class="my-project__note">{{ __('This project was cancelled. Get in touch if you\'d like to reopen it.') }}</p>
                    <a href="{{ route('contact') }}" class="btn btn--outline-dark btn--sm">{{ __('Contact us') }}</a>
                @else
                    <ul class="project-steps">
                        <li @class(['project-step', 'is-done' => $signed])>
                            <span class="project-step__mark">
                                @if ($signed)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                @endif
                            </span>
                            <span class="project-step__label">{{ __('Mandate signed') }}</span>
                        </li>
                        <li @class(['project-step', 'is-done' => $documentsDone])>
                            <span class="project-step__mark">
                                @if ($documentsDone)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                @endif
                            </span>
                            <span class="project-step__label">{{ __('Documents uploaded') }}</span>
                        </li>
                        <li @class(['project-step', 'is-done' => $paid])>
                            <span class="project-step__mark">
                                @if ($paid)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                @endif
                            </span>
                            <span class="project-step__label">{{ __('Payment') }}</span>
                        </li>
                    </ul>

                    @if ($paid)
                        <dl class="my-project__meta">
                            <div class="my-project__meta-row">
                                <dt>{{ __('Plan') }}</dt>
                                <dd>{{ __('Creator Pack') }} &middot; €333 / {{ __('year') }}</dd>
                            </div>
                            @if ($renewsAt)
                                <div class="my-project__meta-row">
                                    <dt>{{ __('Next renewal') }}</dt>
                                    <dd>{{ $renewsAt->isoFormat('D MMMM YYYY') }}</dd>
                                </div>
                            @endif
                            <div class="my-project__meta-row">
                                <dt>{{ __('EU Responsible Person') }}</dt>
                                <dd class="my-project__pending">{{ __('Issued within 24 h, emailed to you') }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="my-project__resume-text">{{ __('Your project isn\'t finished yet. Pick up right where you left off.') }}</p>
                        <a href="{{ route('get-started.starter.journey', ['dossier' => $submission->resume_token]) }}" class="btn btn--coral">{{ __('Resume my project') }}</a>
                    @endif

                    @if (($signed && $submission->contract?->signed_file_path) || $submission->uploadedDocuments->isNotEmpty())
                        <h2 class="my-project__heading">{{ __('Your documents') }}</h2>
                        <ul class="dossier__files">
                            @if ($signed && $submission->contract?->signed_file_path)
                                <li class="dossier__file">
                                    <svg class="dossier__file-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="dossier__file-name">{{ __('Signed Responsible Person mandate') }}</span>
                                    <a class="dossier__download" href="{{ route('get-started.starter.mandate', ['dossier' => $submission->resume_token]) }}">{{ __('Download') }}</a>
                                </li>
                            @endif
                            @foreach ($submission->uploadedDocuments as $doc)
                                <li class="dossier__file">
                                    <svg class="dossier__file-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="dossier__file-name">{{ $doc->type->label() }} <span class="dossier__file-sub">&middot; {{ $doc->original_filename }}</span></span>
                                    <a class="dossier__download" href="{{ route('get-started.starter.document', ['dossier' => $submission->resume_token, 'document' => $doc->id]) }}">{{ __('Download') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>

            <p class="my-project__support">{{ __('Need anything?') }} <a href="{{ route('contact') }}">{{ __('Contact us') }}</a> · {{ __('real humans, happy to help.') }}</p>
        </div>
    </section>
@endsection
