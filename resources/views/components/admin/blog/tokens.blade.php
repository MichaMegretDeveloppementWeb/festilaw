@props(['input', 'output'])
{{-- Tokens d'un appel a un modele : une fleche vers le haut pour ce qui lui a ete envoye, une fleche vers le
     bas pour ce qu'il a ecrit, chacune dans sa couleur, puis le mot « tokens ». Le detail est dit aux lecteurs
     d'ecran et au survol. Un nombre ne se coupe jamais en deux lignes ; faute de place, c'est entre deux
     nombres que la ligne passe. --}}
@php
    $sent = number_format((int) $input, 0, ',', ' ');
    $written = number_format((int) $output, 0, ',', ' ');
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-x-2 tabular-nums']) }}>
    <span class="inline-flex items-center gap-0.5 whitespace-nowrap font-medium text-blue-700 dark:text-blue-400" title="{{ __('Tokens envoyés au modèle') }}" aria-hidden="true">
        <x-ui::icon name="arrow-up" class="h-3 w-3" stroke-width="2.5" />{{ $sent }}
    </span>
    <span class="inline-flex items-center gap-0.5 whitespace-nowrap font-medium text-emerald-700 dark:text-emerald-400" title="{{ __('Tokens écrits par le modèle') }}" aria-hidden="true">
        <x-ui::icon name="arrow-down" class="h-3 w-3" stroke-width="2.5" />{{ $written }}
    </span>
    <span class="font-normal text-muted" aria-hidden="true">{{ __('tokens') }}</span>
    <span class="sr-only">{{ __(':input tokens envoyés au modèle, :output tokens écrits par le modèle', ['input' => $sent, 'output' => $written]) }}</span>
</span>
