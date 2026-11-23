@props(['nodes' => []])
{{-- Structured data: one context, several types via a graph. Rendered as pretty JSON.
     Built in a PHP block so Blade does not treat the schema.org keys as directives.
     JSON_HEX_TAG and JSON_HEX_AMP: a title, a description or a category name holding "</script>" would otherwise
     close this block and run what follows (audit of 23/11; these texts can come from the AI generation). --}}
@php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => array_merge([
            [
                '@type' => ['Organization', 'LegalService'],
                '@id' => url('/').'#organization',
                'name' => config('app.name'),
                'legalName' => 'Festilaw B.V.',
                'url' => url('/'),
                'logo' => asset('images/logo-festilaw-272.png'),
                'description' => __('Your GPSR Responsible Person in the EU for non-EU sellers.'),
                'email' => config('festilaw.contact_email'),
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => 'Spoorstraat 30A',
                    'postalCode' => '6511 AN',
                    'addressLocality' => 'Nijmegen',
                    'addressCountry' => 'NL',
                ],
                'identifier' => [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'KvK',
                    'value' => '77058720',
                ],
                'vatID' => 'NL860886761B01',
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'email' => config('festilaw.contact_email'),
                    'contactType' => 'customer support',
                    'availableLanguage' => ['en', 'fr', 'es'],
                ],
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
{!! json_encode($jsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
</script>
