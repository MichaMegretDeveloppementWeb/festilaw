{{-- Selecteur de langue. Sur une page publique (une adresse par langue) : lien direct vers la meme page
     dans chaque langue. Sur une page sans route (404) : l'accueil de chaque langue. Ailleurs (tunnel,
     espace client) : bascule en session via locale.switch. Cf. LocalizedUrlGenerator::switcherLinks(). --}}
@inject('localizedUrls', 'App\Services\Web\Localization\LocalizedUrlGenerator')
@php
    $labels = config('festilaw.locale_labels');
@endphp
<div {{ $attributes->merge(['class' => 'lang-switch']) }}>
    @foreach ($localizedUrls->switcherLinks() as $code => $url)
        <a
            href="{{ $url }}"
            @class(['is-active' => app()->getLocale() === $code])
        >{{ $labels[$code] ?? strtoupper($code) }}</a>
    @endforeach
</div>
