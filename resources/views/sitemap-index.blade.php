{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{!! '<?xml-stylesheet type="text/css" href="'.e($stylesheet).'"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($files as $file)
    <sitemap>
        <loc>{{ $file->url }}</loc>
@if ($file->lastmod !== null)
        <lastmod>{{ $file->lastmod }}</lastmod>
@endif
    </sitemap>
@endforeach
</sitemapindex>
{{-- Rien avant la declaration XML, qui doit ouvrir le fichier : ce commentaire reste donc a la fin.
     Index du sitemap : l'adresse declaree a la Search Console et dans robots.txt. Il renvoie aux fichiers
     des pages, des articles et des categories. --}}
