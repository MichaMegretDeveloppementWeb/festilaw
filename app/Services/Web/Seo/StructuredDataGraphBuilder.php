<?php

declare(strict_types=1);

namespace App\Services\Web\Seo;

use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Les donnees structurees (schema.org) d'une page publique, en un seul graphe ou chaque element cite les
 * autres par leur identifiant : l'organisation, le site, la page elle-meme (son type, sa langue, son fil
 * d'Ariane) et les entites propres a la page (service, blog, article). Une page noindex ne garde que
 * l'organisation et le site. Aucune requete : tout vient de la page et de la configuration.
 */
final readonly class StructuredDataGraphBuilder
{
    /**
     * @param  list<string>  $locales  langues du site
     */
    public function __construct(
        private UrlGenerator $url,
        private LocalizedUrlGenerator $localizedUrls,
        private string $siteName,
        private string $contactEmail,
        private array $locales,
    ) {}

    /**
     * Le titre, la description et l'adresse arrivent des sections du layout, que Blade a deja echappees :
     * ils sont decodes ici, sinon une apostrophe sortirait « &#039; » dans le JSON.
     *
     * @param  string|list<string>  $pageType  type schema.org de la page (WebPage, AboutPage, FAQPage...)
     * @param  array<string, mixed>  $pageProperties  proprietes propres a la page (questions, sujet)
     * @param  list<array{name: string, url: string}>  $breadcrumbs  fil d'Ariane affiche sur la page
     * @param  list<array<string, mixed>>  $nodes  entites propres a la page
     * @return array{'@context': string, '@graph': list<array<string, mixed>>}
     */
    public function graph(
        string $url,
        string $locale,
        string $name,
        ?string $description,
        string $robots,
        string|array $pageType = 'WebPage',
        array $pageProperties = [],
        array $breadcrumbs = [],
        array $nodes = [],
    ): array {
        $graph = [$this->organization(), $this->website()];

        if (! str_contains($robots, 'noindex')) {
            $url = $this->decoded($url);
            $description = $description === null ? null : $this->decoded($description);

            $graph[] = $this->withoutNulls([
                '@type' => $pageType,
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $this->decoded($name),
                'description' => $description === '' ? null : $description,
                'inLanguage' => $locale,
                'isPartOf' => $this->websiteReference(),
                'breadcrumb' => $breadcrumbs === [] ? null : ['@id' => $url.'#breadcrumb'],
                ...$pageProperties,
            ]);

            if ($breadcrumbs !== []) {
                $graph[] = $this->breadcrumbList($url, $breadcrumbs);
            }

            array_push($graph, ...$nodes);
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /**
     * @return array{'@id': string}
     */
    public function organizationReference(): array
    {
        return ['@id' => $this->root().'#organization'];
    }

    /**
     * Le blog d'une langue : chaque langue a le sien, a l'adresse de son accueil.
     *
     * @return array{'@id': string}
     */
    public function blogReference(string $locale): array
    {
        return ['@id' => $this->localizedUrls->route('blog.index', [], $locale).'#blog'];
    }

    /**
     * @return array{'@id': string}
     */
    public function serviceReference(): array
    {
        return ['@id' => $this->root().'#service'];
    }

    /**
     * Les questions d'une FAQ affichee sur la page, pour la propriete mainEntity d'une page FAQPage.
     *
     * @param  list<array{q: string, a: string}>  $faqItems  questions et reponses, deja traduites
     * @return list<array<string, mixed>>
     */
    public function questions(array $faqItems): array
    {
        return array_map(static fn (array $item): array => [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
        ], $faqItems);
    }

    /**
     * Le service de Festilaw, decrit sur la page Services et repris avec ses offres sur la page des tarifs.
     * Les prix sont annuels (une offre « sur devis » n'en a pas et ne figure pas ici).
     *
     * @param  array<string, int>  $annualPricesInCents  nom de l'offre => prix annuel en centimes
     * @return array<string, mixed>
     */
    public function service(array $annualPricesInCents = []): array
    {
        $offers = [];

        foreach ($annualPricesInCents as $offerName => $cents) {
            $price = number_format($cents / 100, 2, '.', '');

            $offers[] = [
                '@type' => 'Offer',
                'name' => $offerName,
                'price' => $price,
                'priceCurrency' => 'EUR',
                'priceSpecification' => [
                    '@type' => 'UnitPriceSpecification',
                    'price' => $price,
                    'priceCurrency' => 'EUR',
                    'unitCode' => 'ANN',
                    'unitText' => 'year',
                ],
                'url' => $this->localizedUrls->route('pricing'),
            ];
        }

        return $this->withoutNulls([
            '@type' => 'Service',
            '@id' => $this->root().'#service',
            'serviceType' => 'GPSR Responsible Person',
            'name' => __('GPSR compliance services'),
            'provider' => $this->organizationReference(),
            'areaServed' => 'EU',
            'url' => $this->localizedUrls->route('services'),
            'offers' => $offers === [] ? null : $offers,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function organization(): array
    {
        return [
            '@type' => ['Organization', 'LegalService'],
            '@id' => $this->root().'#organization',
            'name' => $this->siteName,
            'legalName' => 'Festilaw B.V.',
            'url' => $this->root(),
            'logo' => $this->url->asset('images/logo-festilaw-272.png'),
            'description' => __('Your GPSR Responsible Person in the EU for non-EU sellers.'),
            'email' => $this->contactEmail,
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
                'email' => $this->contactEmail,
                'contactType' => 'customer support',
                'availableLanguage' => $this->locales,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => $this->root().'#website',
            'name' => $this->siteName,
            'url' => $this->root(),
            'inLanguage' => $this->locales,
            'publisher' => $this->organizationReference(),
        ];
    }

    /**
     * @return array{'@id': string}
     */
    private function websiteReference(): array
    {
        return ['@id' => $this->root().'#website'];
    }

    /**
     * @param  list<array{name: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    private function breadcrumbList(string $url, array $breadcrumbs): array
    {
        $items = [];

        foreach (array_values($breadcrumbs) as $index => $breadcrumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $breadcrumb['name'],
                'item' => $breadcrumb['url'],
            ];
        }

        return ['@type' => 'BreadcrumbList', '@id' => $url.'#breadcrumb', 'itemListElement' => $items];
    }

    private function root(): string
    {
        return $this->url->to('/');
    }

    private function decoded(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function withoutNulls(array $node): array
    {
        return array_filter($node, static fn (mixed $value): bool => $value !== null);
    }
}
