@extends('layouts.web')

@section('title', __('Get started · Festilaw'))
@section('meta_description', __('Choose your GPSR compliance plan: Creator, Pro or Scale, and get your EU Responsible Person set up.'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/index.css')
@endpush

@section('content')
    <section class="page-hero page-hero--tight">
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">{{ __('Get started') }}</span>
            <h1 class="page-hero__title">{!! __('Choose your :path', ['path' => '<span class="page-hero__title-em">'.e(__('path')).'</span>']) !!}</h1>
            <p class="page-hero__lead">{{ __('Pick where you fit and start now. Your EU Responsible Person is three simple steps away.') }}</p>
        </div>
    </section>

    <section class="start">
        <div class="start__inner">
            {{-- Le parcours en 3 etapes : on signale d'emblee qu'on demarre un process. --}}
            <ol class="start-steps">
                <li class="start-step">
                    <span class="start-step__num">1</span>
                    <span class="start-step__label">{{ __('Choose your plan') }}</span>
                </li>
                <li class="start-step">
                    <span class="start-step__num">2</span>
                    <span class="start-step__label">{{ __('Sign your mandate & upload your documents') }}</span>
                </li>
                <li class="start-step">
                    <span class="start-step__num">3</span>
                    <span class="start-step__label">{{ __('Get your EU Responsible Person within 24 h') }}</span>
                </li>
            </ol>

            <h2 class="start-heading">{{ __('Pick your starting point') }}</h2>
            <div class="start-paths">
                <a href="{{ route('get-started.starter') }}" class="start-path">
                    <span class="start-path__body">
                        <span class="start-path__name">{{ __('Creator Pack') }}</span>
                        <span class="start-path__who">{{ __('Small creators · up to 9 products') }}</span>
                    </span>
                    <span class="start-path__price">€333<span class="start-path__per">/{{ __('year') }}</span></span>
                    <span class="start-path__go">{{ __('Start') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </span>
                </a>

                <a href="{{ route('get-started.pro') }}" class="start-path start-path--featured">
                    <span class="start-path__badge">{{ __('Most popular') }}</span>
                    <span class="start-path__body">
                        <span class="start-path__name">{{ __('Pro Pack') }}</span>
                        <span class="start-path__who">{{ __('Growing brands · 10 to 100 products') }}</span>
                    </span>
                    <span class="start-path__price">€1,200<span class="start-path__per">/{{ __('year') }}</span></span>
                    <span class="start-path__go">{{ __('Start') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </span>
                </a>

                <a href="{{ route('get-started.scale') }}" class="start-path">
                    <span class="start-path__body">
                        <span class="start-path__name">{{ __('Scale Pack') }}</span>
                        <span class="start-path__who">{{ __('100+ products · starts with a full audit') }}</span>
                    </span>
                    <span class="start-path__price">{{ __('Custom') }}</span>
                    <span class="start-path__go">{{ __('Start') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </span>
                </a>
            </div>

            <p class="start__reassure">{{ __('No account needed · secure payment · GDPR-compliant.') }}</p>
        </div>
    </section>
@endsection
