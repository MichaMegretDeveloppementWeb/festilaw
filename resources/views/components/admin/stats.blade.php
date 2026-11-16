@props(['columns' => 3])
{{-- Bande de chiffres : des cellules separees par des filets fins, entre deux filets horizontaux. Les filets
     viennent de l'espace d'un pixel entre les cellules, qui laisse voir la couleur des bordures du kit : ils
     restent justes quel que soit le nombre de colonnes. Le nombre de cellules doit remplir la derniere ligne
     (2, 3 ou 4 cellules), sinon une case vide paraitrait grise.
     Sur telephone : une colonne pour 3 cellules, deux pour 2 ou 4. Contenu : des <x-admin.stats.item>. --}}
@php
    $grid = match ((int) $columns) {
        2 => 'grid-cols-2',
        4 => 'grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-1 sm:grid-cols-3',
    };
@endphp
<dl {{ $attributes->class(['-mx-4 grid gap-px border-y border-default bg-[var(--ui-border)] sm:-mx-5', $grid]) }}>
    {{ $slot }}
</dl>
