@extends('layouts.web')

@section('title', $page->metaTitle ?? __(':title · Festilaw', ['title' => $page->title]))
@if ($page->metaDescription)
    @section('meta_description', $page->metaDescription)
@endif
@section('canonical', $page->url)
@if ($isPreview ?? false)
    @section('robots', 'noindex, nofollow')
@endif
@section('og_type', 'article')
@if ($page->shareImageUrl)
    @section('og_image', $page->shareImageUrl)
    @if ($page->shareImageAlt)
        @section('og_image_alt', $page->shareImageAlt)
    @endif
@endif

@php
    $locale = app()->getLocale();
    $breadcrumbs = array_values(array_filter([
        ['name' => __('Home'), 'url' => localized_route('home')],
        ['name' => __('Blog'), 'url' => localized_route('blog.index')],
        $page->category ? ['name' => $page->category->name, 'url' => $page->category->url] : null,
        ['name' => $page->title, 'url' => $page->url],
    ]));
    $jsonLdNodes = array_values(array_filter([$articleNode ?? null]));
@endphp

@push('meta')
    <meta property="article:published_time" content="{{ $page->publishedAt->toIso8601String() }}">
    <meta property="article:modified_time" content="{{ ($page->updatedAt ?? $page->publishedAt)->toIso8601String() }}">
    @if ($page->category)
        <meta property="article:section" content="{{ $page->category->name }}">
    @endif
@endpush

@push('styles')
    @vite('resources/css/web/blog/article.css')
@endpush

@section('content')
    @if ($isPreview ?? false)
        <p class="article-preview" role="status">{{ __('Preview · this is how the article will look once published.') }}</p>
    @endif

    <article class="article">
        <header class="article-hero">
            <x-web.breadcrumb :items="$breadcrumbs" />
            <div class="article-hero__inner">
                <h1 class="article-hero__title">{{ $page->title }}</h1>
                @if ($page->excerpt)
                    <p class="article-hero__lead">{{ $page->excerpt }}</p>
                @endif
                {{-- Auteur et date sur une ligne : une icone et la valeur. Le libelle reste lu par les lecteurs
                     d'ecran. La qualite de l'auteur n'est plus affichee (elle reste dans les donnees enrichies). --}}
                <p class="article-hero__meta">
                    <span class="article-hero__meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        <span class="visually-hidden">{{ __('By') }}</span>
                        {{ $page->author->name }}
                    </span>
                    <span class="article-hero__meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                        <span class="visually-hidden">{{ __('Published on') }}</span>
                        <time datetime="{{ $page->publishedAt->toDateString() }}">{{ $page->publishedLabel($locale) }}</time>
                    </span>
                    @if ($page->updatedAt)
                        <span class="article-hero__meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"/></svg>
                            {{ __('Updated on') }} <time datetime="{{ $page->updatedAt->toDateString() }}">{{ $page->updatedLabel($locale) }}</time>
                        </span>
                    @endif
                </p>
            </div>
        </header>

        @if ($page->picture)
            <div class="article__cover">
                <x-web.blog.picture :picture="$page->picture" sizes="(min-width: 1280px) 972px, (min-width: 400px) 90vw, calc(100vw - 40px)" :eager="true" class="article__cover-image" />
            </div>
        @endif

        <div class="article__main">
            {{-- HTML produit et echappe par le serveur a l'enregistrement (BlogContentRenderer) : rendu tel quel. --}}
            <div class="prose">{!! $page->html !!}</div>

            @if ($page->sources !== [])
                <section class="article__sources" aria-labelledby="article-sources">
                    <h2 id="article-sources" class="article__sources-title">{{ __('Sources') }}</h2>
                    <ol class="article__sources-list">
                        @foreach ($page->sources as $source)
                            <li><a href="{{ $source['url'] }}" target="_blank" rel="noopener">{{ $source['label'] }}</a></li>
                        @endforeach
                    </ol>
                </section>
            @endif

            <aside class="article-cta" aria-labelledby="article-cta-title">
                <h2 id="article-cta-title" class="article-cta__title">{{ __('Does this apply to your products?') }}</h2>
                <p class="article-cta__text">{{ __('Check in 30 seconds whether you need an EU Responsible Person, or see what Festilaw includes.') }}</p>
                <div class="article-cta__actions">
                    <a data-track-event="article.to_quiz" href="{{ localized_route('home') }}#quiz" class="btn btn--coral">{{ __('Take the eligibility quiz') }}</a>
                    <a data-track-event="article.to_pricing" href="{{ localized_route('pricing') }}" class="btn btn--outline-dark">{{ __('View plans and pricing') }}</a>
                </div>
            </aside>
        </div>
    </article>

    @if ($page->related !== [])
        <section class="article-related" aria-labelledby="article-related-title">
            <div class="article-related__inner">
                <h2 id="article-related-title" class="article-related__title">{{ __('Related articles') }}</h2>
                <div class="post-grid">
                    @foreach ($page->related as $card)
                        @include('web.blog.partials.card', ['card' => $card, 'heading' => 'h3'])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
