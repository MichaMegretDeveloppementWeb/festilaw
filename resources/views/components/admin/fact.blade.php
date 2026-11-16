@props(['label', 'icon' => null])
{{-- Une donnee d'un resume : une icone, un petit libelle, la valeur dessous. Se range en grille avec ses
     voisines, a la place d'une liste « libelle a gauche, valeur a droite » d'une ligne par donnee.
     $icon : nom d'une icone du kit (facultatif). Le slot `symbol` la remplace par un dessin du projet. --}}
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <p class="flex items-center gap-1.5 text-[12px] font-medium text-muted">
        @if (isset($symbol))
            {{ $symbol }}
        @elseif ($icon)
            <x-ui::icon :name="$icon" class="h-3.5 w-3.5 shrink-0" />
        @endif
        <span class="truncate">{{ $label }}</span>
    </p>
    <div class="mt-1 text-[14px] font-semibold leading-snug text-primary">{{ $slot }}</div>
</div>
