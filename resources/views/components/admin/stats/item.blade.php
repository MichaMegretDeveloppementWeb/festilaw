@props(['label', 'icon' => null, 'size' => 'lg'])
{{-- Une cellule de la bande de chiffres (<x-admin.stats>) : une icone et un libelle de 12 px, puis la valeur.
     $size : 'lg' pour un chiffre qui sert de repere (26 px), 'sm' pour une donnee de lecture (14 px).
     Le slot par defaut est la valeur ; le slot `extra` ce qui se lit dessous (une phrase, une barre, un lien).
     Le slot `symbol` remplace l'icone du kit par un dessin du projet. --}}
<div {{ $attributes->class(['flex min-w-0 flex-col bg-surface px-4 py-3.5 sm:px-5 sm:py-5']) }}>
    <dt class="flex items-center gap-1.5 text-[12px] font-medium text-muted">
        @isset($symbol)
            {{ $symbol }}
        @elseif ($icon)
            <x-ui::icon :name="$icon" class="h-4 w-4 shrink-0" />
        @endisset
        <span class="truncate">{{ $label }}</span>
    </dt>
    <dd @class([
        'mt-1.5 min-w-0 text-primary sm:mt-2',
        'text-[20px] sm:text-[26px] font-semibold leading-none tracking-tight tabular-nums' => $size === 'lg',
        'text-[14px] font-medium leading-snug' => $size === 'sm',
    ])>{{ $slot }}</dd>
    @isset($extra)
        <div class="mt-2 min-w-0 text-[12px] leading-relaxed text-muted">{{ $extra }}</div>
    @endisset
</div>
