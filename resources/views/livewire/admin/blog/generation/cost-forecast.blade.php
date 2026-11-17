{{-- Fourchette de cout d'un article, partagee par les reglages et la fenetre de lancement. $advice : ce que
     l'ecran conseille quand le haut de la fourchette depasse le plafond. --}}
<x-admin.stats :columns="2" data-forecast-range>
    <x-admin.stats.item icon="calculator" :label="__('Coût estimé d\'un article')" size="sm">
        {{ __('Entre :low et :high', ['low' => $forecast['low'], 'high' => $forecast['high']]) }}
    </x-admin.stats.item>
    <x-admin.stats.item icon="shield-check" :label="__('Plafond par article')" size="sm">{{ $forecast['cap'] ?? '–' }}</x-admin.stats.item>
</x-admin.stats>

@if ($forecast['exceeds'])
    <x-ui::alert type="warning" data-forecast-above-cap :title="__('Le coût estimé dépasse le plafond par article')">
        {{ $advice }}
    </x-ui::alert>
@endif
