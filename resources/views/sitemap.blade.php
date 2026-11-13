{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $alternates)
@foreach ($alternates as $url)
    <url>
        <loc>{{ $url }}</loc>
@foreach ($alternates as $locale => $alternateUrl)
        <xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $alternateUrl }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $alternates[$defaultLocale] }}"/>
    </url>
@endforeach
@endforeach
</urlset>
