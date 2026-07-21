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
                <h1 class="my-project__title">{{ __('Your') }} <span class="my-project__title-em">{{ __($project->packLabel) }}</span></h1>
                <p class="my-project__intro">{{ __('Your compliance project in one place. Keep this link private · it\'s your secure access, no account needed.') }}</p>
            </header>

            <div class="my-project__card">
                <div class="my-project__statusbar">
                    <span @class([
                        'my-project__badge',
                        'is-active' => $project->paid,
                        'is-cancelled' => $project->cancelled,
                        'is-progress' => ! $project->paid && ! $project->cancelled,
                    ])>{{ $project->paid ? __('Active') : ($project->cancelled ? __('Cancelled') : __('In progress')) }}</span>
                    <span class="my-project__ref">{{ __('Ref.') }} {{ $project->reference }}</span>
                </div>

                @if ($project->cancelled)
                    <p class="my-project__note">{{ __('This project was cancelled. Get in touch if you\'d like to reopen it.') }}</p>
                    <a href="{{ route('contact') }}" class="btn btn--outline-dark btn--sm">{{ __('Contact us') }}</a>
                @else
                    <ul class="project-steps">
                        <li @class(['project-step', 'is-done' => $project->signed])>
                            <span class="project-step__mark">
                                @if ($project->signed)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                @endif
                            </span>
                            <span class="project-step__label">{{ __('Mandate signed') }}</span>
                        </li>
                        <li @class(['project-step', 'is-done' => $project->documentsDone])>
                            <span class="project-step__mark">
                                @if ($project->documentsDone)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                @endif
                            </span>
                            <span class="project-step__label">{{ __('Documents uploaded') }}</span>
                        </li>
                        <li @class(['project-step', 'is-done' => $project->paid])>
                            <span class="project-step__mark">
                                @if ($project->paid)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                @endif
                            </span>
                            <span class="project-step__label">{{ __('Payment') }}@if ($project->paid && $project->paidAmountCents !== null) <span class="project-step__amount">(€{{ number_format($project->paidAmountCents / 100, $project->paidAmountCents % 100 === 0 ? 0 : 2) }}@if ($project->paidAt) &middot; {{ $project->paidAt->isoFormat('D MMMM YYYY') }}@endif)</span>@endif</span>
                        </li>
                    </ul>

                    @if ($project->paid)
                        <dl class="my-project__meta">
                            <div class="my-project__meta-row">
                                <dt>{{ __('Plan') }}</dt>
                                <dd>{{ __($project->packLabel) }} &middot; €{{ number_format($project->annualCents / 100) }} / {{ __('year') }}</dd>
                            </div>
                            @if ($project->renewsAt)
                                <div class="my-project__meta-row">
                                    <dt>{{ __('Next renewal') }}</dt>
                                    <dd>{{ $project->renewsAt->isoFormat('D MMMM YYYY') }}</dd>
                                </div>
                            @endif
                            <div class="my-project__meta-row">
                                <dt>{{ __('EU Responsible Person') }}</dt>
                                @if ($project->euRpAddress)
                                    <dd style="white-space: pre-wrap;">{{ $project->euRpAddress }}</dd>
                                @else
                                    <dd class="my-project__pending">{{ __('Issued within 24 h, emailed to you') }}</dd>
                                @endif
                            </div>
                        </dl>
                    @else
                        <p class="my-project__resume-text">{{ __('Your project isn\'t finished yet. Pick up right where you left off.') }}</p>
                        <a href="{{ $project->resumeUrl }}" class="btn btn--coral">{{ __('Resume my project') }}</a>
                    @endif

                    @if ($project->hasDownloads())
                        <h2 class="my-project__heading">{{ __('Your documents') }}</h2>
                        <ul class="dossier__files">
                            @if ($project->mandateDownloadUrl)
                                <li class="dossier__file">
                                    <svg class="dossier__file-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="dossier__file-name">{{ __('Signed Responsible Person mandate') }}</span>
                                    <a class="dossier__download" href="{{ $project->mandateDownloadUrl }}">{{ __('Download') }}</a>
                                </li>
                            @endif
                            @foreach ($project->documents as $doc)
                                <li class="dossier__file">
                                    <svg class="dossier__file-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="dossier__file-name">{{ $doc->label }} <span class="dossier__file-sub">&middot; {{ $doc->filename }}</span></span>
                                    <a class="dossier__download" href="{{ $doc->downloadUrl }}">{{ __('Download') }}</a>
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
