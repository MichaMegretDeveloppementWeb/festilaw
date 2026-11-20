@props(['items' => []])
{{-- items : [['name' => 'Home', 'url' => '...', 'track' => facultatif], ...]. Le dernier element = page courante.
     Chaque lien porte un libelle fixe pour la mesure d'audience, le meme dans les trois langues. --}}
<nav class="breadcrumb" aria-label="{{ __('Breadcrumb') }}">
    <ol class="breadcrumb__list">
        @foreach ($items as $item)
            <li class="breadcrumb__item">
                @unless ($loop->last)
                    <a data-track-label="{{ $item['track'] ?? ($loop->first ? 'Fil d\'Ariane, Accueil' : 'Fil d\'Ariane, page parente') }}" href="{{ $item['url'] }}" class="breadcrumb__link">{{ $item['name'] }}</a>
                    <span class="breadcrumb__sep" aria-hidden="true">/</span>
                @else
                    <span class="breadcrumb__current" aria-current="page">{{ $item['name'] }}</span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
