{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $page)
@foreach ($page['urls'] as $url)
    <url>
        <loc>{{ $url }}</loc>
@if ($page['lastmod'] !== null)
        <lastmod>{{ $page['lastmod'] }}</lastmod>
@endif
@foreach ($page['urls'] as $locale => $alternateUrl)
        <xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $alternateUrl }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $page['urls'][$defaultLocale] }}"/>
    </url>
@endforeach
@endforeach
</urlset>
