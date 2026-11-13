@props(['column', 'sorted' => null, 'direction' => 'desc'])
{{-- En-tete d'une colonne triable. Un vrai bouton (atteignable au clavier) qui appelle sortBy() du
     composant de la liste ; la cellule du kit annonce le sens du tri aux lecteurs d'ecran.
     $sorted : la colonne triee en ce moment ; $direction : 'asc' ou 'desc'. --}}
@php($active = $sorted === $column)
<x-ui::table.header-cell :sorted="$active ? $direction : null" {{ $attributes }}>
    <button type="button" wire:click="sortBy('{{ $column }}')"
        @class(['group -my-1 inline-flex items-center gap-x-1 rounded py-1 uppercase transition-colors hover:text-primary', 'text-primary' => $active])>
        {{ $slot }}
        @if ($active)
            <x-ui::icon name="chevron-down" @class(['h-3 w-3', 'rotate-180' => $direction === 'asc']) stroke-width="2.5" />
        @else
            <x-ui::icon name="chevron-up-down" class="h-3 w-3 opacity-40 transition-opacity group-hover:opacity-100" stroke-width="2" />
        @endif
    </button>
</x-ui::table.header-cell>
