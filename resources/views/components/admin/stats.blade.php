@props(['columns' => 3, 'tag' => 'dl'])
{{-- Bande de chiffres : des cellules separees par des filets fins, entre deux filets horizontaux. Les filets
     viennent de l'espace d'un pixel entre les cellules, qui laisse voir la couleur des bordures du kit : ils
     restent justes quel que soit le nombre de colonnes. Le nombre de cellules doit remplir la derniere ligne
     (2, 3 ou 4 cellules), sinon une case vide paraitrait grise.
     Sur telephone : deux colonnes, la derniere cellule d'un nombre impair prend toute la ligne, et les cellules
     se resserrent (une bande ne doit pas repousser la page). Contenu : des <x-admin.stats.item>.
     La bande part du bord du titre de la page, et les cellules de la premiere colonne n'ont pas de marge a
     gauche : leur texte s'aligne sur ce bord (decision de Micha du 19/11). La premiere colonne change avec le
     nombre de colonnes, d'ou une regle par mise en page.
     $tag : 'dl' pour des chiffres a lire, 'div' quand les cellules sont des boutons (filtre par statut). --}}
@php
    $grid = match ((int) $columns) {
        2 => 'grid-cols-2 [&>*:nth-child(odd)]:pl-0',
        4 => 'grid-cols-2 lg:grid-cols-4 max-lg:[&>*:nth-child(odd)]:pl-0 lg:[&>*:nth-child(4n+1)]:pl-0',
        default => 'grid-cols-2 sm:grid-cols-3 max-sm:[&>*:last-child:nth-child(odd)]:col-span-2 max-sm:[&>*:nth-child(odd)]:pl-0 sm:[&>*:nth-child(3n+1)]:pl-0',
    };
@endphp
<{{ $tag }} {{ $attributes->class(['grid gap-px border-y border-default bg-[var(--ui-border)]', $grid]) }}>
    {{ $slot }}
</{{ $tag }}>
