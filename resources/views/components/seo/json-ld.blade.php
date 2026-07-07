@props(['nodes' => []])
{{-- Structured data: one context, several types via a graph. Rendered as pretty JSON.
     Built in a PHP block so Blade does not treat the schema.org keys as directives. --}}
@php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => array_merge([
            [
                '@type' => 'Organization',
                'name' => config('app.name'),
                'url' => url('/'),
                'description' => 'Your GPSR Responsible Person in the EU for non-EU sellers.',
                'email' => 'team@festilaw.com',
            ],
            [
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => url('/'),
            ],
        ], $nodes),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($jsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
