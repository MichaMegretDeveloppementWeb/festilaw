{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{!! '<?xml-stylesheet type="text/css" href="'.e($stylesheet).'"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($entries as $entry)
@foreach ($entry->urls as $url)
    <url>
        <loc>{{ $url }}</loc>
@if ($entry->lastmod !== null)
        <lastmod>{{ $entry->lastmod }}</lastmod>
@endif
@foreach ($entry->urls as $locale => $alternateUrl)
        <xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $alternateUrl }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $entry->urls[$defaultLocale] }}"/>
    </url>
@endforeach
@endforeach
</urlset>
{{-- Rien avant la declaration XML, qui doit ouvrir le fichier : ce commentaire reste donc a la fin.
     Un fichier du sitemap (pages, articles ou categories). La feuille CSS ne sert qu'a un humain qui l'ouvre
     dans un navigateur, les robots l'ignorent. --}}
