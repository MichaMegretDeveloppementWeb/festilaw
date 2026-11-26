@extends('layouts.web')

@section('title', $page->pagination->currentPage > 1
    ? __('Blog · page :page · Festilaw', ['page' => $page->pagination->currentPage])
    : __('Blog · GPSR and EU product safety guides · Festilaw'))
@section('meta_description', __('Practical guides and news on EU product safety rules for sellers outside the EU.'))
@section('canonical', $page->url)
@if ($page->isEmpty())
    {{-- Tant qu'aucun article n'est publie, la page existe mais ne doit pas etre indexee. --}}
    @section('robots', 'noindex, follow')
@endif

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => localized_route('home')],
        ['name' => __('Blog'), 'url' => localized_route('blog.index')],
    ];
    $jsonLdPageType = 'CollectionPage';
    $jsonLdPage = ['mainEntity' => ['@id' => $blogNode['@id']]];
    $jsonLdNodes = [$blogNode];
@endphp

@push('styles')
    @vite('resources/css/web/blog/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <x-web.breadcrumb :items="$breadcrumbs" />
        <div class="page-hero__inner">
            <h1 class="page-hero__title">{{ __('Selling in the EU,') }} <span class="page-hero__title-em">{{ __('explained') }}</span></h1>
            <p class="page-hero__lead">{{ __('Practical guides and news on EU product safety rules for sellers outside the EU.') }}</p>
        </div>
    </section>

    <section class="blog-list">
        <div class="blog-list__inner">
            @if ($page->isEmpty())
                <p class="blog-list__empty">{{ __('Our first articles are on their way. Come back soon.') }}</p>
            @else
                @include('web.blog.partials.categories', ['categories' => $page->categories, 'allActive' => true])

                @if ($page->featured)
                    @include('web.blog.partials.featured', ['card' => $page->featured])
                @endif

                @if ($page->cards !== [])
                    <div class="post-grid">
                        @foreach ($page->cards as $card)
                            @include('web.blog.partials.card', ['card' => $card, 'heading' => 'h2'])
                        @endforeach
                    </div>
                @endif

                @include('web.blog.partials.pagination', ['pagination' => $page->pagination])
            @endif
        </div>
    </section>

    @include('web.sections.final-cta')
@endsection
