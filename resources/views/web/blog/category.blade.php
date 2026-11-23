@extends('layouts.web')

{{-- Les pages 2 et suivantes portent leur numero, comme celles du blog : sinon Google les compte comme des
     doublons (audit du 23/11). --}}
@section('title', $page->pagination->currentPage > 1
    ? __(':category · page :page · Blog · Festilaw', ['category' => $page->metaTitle ?? $page->name, 'page' => $page->pagination->currentPage])
    : ($page->metaTitle ?? __(':category · Blog · Festilaw', ['category' => $page->name])))
@section('meta_description', $page->metaDescription ?? $page->description ?? __('Practical guides and news on EU product safety rules for sellers outside the EU.'))
@section('canonical', $page->url)

@php
    $breadcrumbs = [
        ['name' => __('Home'), 'url' => localized_route('home')],
        ['name' => __('Blog'), 'url' => localized_route('blog.index'), 'track' => 'Fil d\'Ariane, Blog'],
        ['name' => $page->name, 'url' => $page->pagination->url(1)],
    ];
    $jsonLdNodes = [$collectionNode];
@endphp

@push('styles')
    @vite('resources/css/web/blog/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <x-web.breadcrumb :items="$breadcrumbs" />
        <div class="page-hero__inner">
            <h1 class="page-hero__title">{{ $page->name }}</h1>
            @if ($page->description)
                <p class="page-hero__lead">{{ $page->description }}</p>
            @endif
        </div>
    </section>

    <section class="blog-list">
        <div class="blog-list__inner">
            @include('web.blog.partials.categories', ['categories' => $page->categories, 'allActive' => false])

            <div class="post-grid">
                @foreach ($page->cards as $card)
                    @include('web.blog.partials.card', ['card' => $card, 'heading' => 'h2', 'from' => 'une catégorie'])
                @endforeach
            </div>

            @include('web.blog.partials.pagination', ['pagination' => $page->pagination])
        </div>
    </section>

    @include('web.sections.final-cta')
@endsection
