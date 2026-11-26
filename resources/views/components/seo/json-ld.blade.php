@props([
    'url',
    'name',
    'description' => null,
    'robots' => 'index, follow',
    'pageType' => 'WebPage',
    'page' => [],
    'breadcrumbs' => [],
    'nodes' => [],
])
{{-- Structured data: one context, one graph built by StructuredDataGraphBuilder (organization, site, the page
     itself, its breadcrumb and its own entities, all linked by @id). Rendered as pretty JSON.
     JSON_HEX_TAG and JSON_HEX_AMP: a title, a description or a category name holding "</script>" would otherwise
     close this block and run what follows (audit of 23/11; these texts can come from the AI generation). --}}
@inject('structuredData', 'App\Services\Web\Seo\StructuredDataGraphBuilder')
@php
    $jsonLd = $structuredData->graph(
        url: $url,
        locale: app()->getLocale(),
        name: $name,
        description: $description,
        robots: $robots,
        pageType: $pageType,
        pageProperties: $page,
        breadcrumbs: $breadcrumbs,
        nodes: $nodes,
    );
@endphp
<script type="application/ld+json">
{!! json_encode($jsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
</script>
