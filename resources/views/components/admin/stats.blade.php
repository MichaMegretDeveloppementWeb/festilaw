@props(['columns' => 3, 'tag' => 'dl'])
{{-- Bande de chiffres : des cellules separees par des filets fins, entre deux filets horizontaux. Les filets
     viennent de l'espace d'un pixel entre les cellules, qui laisse voir la couleur des bordures du kit : ils
     restent justes quel que soit le nombre de colonnes. Le nombre de cellules doit remplir la derniere ligne
     (2, 3 ou 4 cellules), sinon une case vide paraitrait grise.
     Sur telephone : deux colonnes, la derniere cellule d'un nombre impair prend toute la ligne, et les cellules
     se resserrent (une bande ne doit pas repousser la page). Contenu : des <x-admin.stats.item>.
     $tag : 'dl' pour des chiffres a lire, 'div' quand les cellules sont des boutons (filtre par statut). --}}
@php
    $grid = match ((int) $columns) {
        2 => 'grid-cols-2',
        4 => 'grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-2 sm:grid-cols-3 max-sm:[&>*:last-child:nth-child(odd)]:col-span-2',
    };
@endphp
<{{ $tag }} {{ $attributes->class(['-mx-4 grid gap-px border-y border-default bg-[var(--ui-border)] sm:-mx-5', $grid]) }}>
    {{ $slot }}
</{{ $tag }}>
