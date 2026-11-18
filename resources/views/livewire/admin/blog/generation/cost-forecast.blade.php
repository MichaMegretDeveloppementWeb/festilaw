{{-- Fourchette de cout d'un article, partagee par les reglages et la fenetre de lancement. Elle n'est qu'une
     estimation : seule la limite (plafond augmente de sa marge) est garantie, appel par appel (decision de
     Micha du 18/11). $advice : ce que l'ecran conseille quand le haut de la fourchette depasse cette limite. --}}
<x-admin.stats :columns="2" data-forecast-range>
    <x-admin.stats.item icon="calculator" :label="__('Coût estimé d\'un article')" size="sm">
        {{ __('Entre :low et :high', ['low' => $forecast['low'], 'high' => $forecast['high']]) }}
        <x-slot:extra>{{ __('Estimation tirée des articles déjà générés. Le coût réel peut en sortir.') }}</x-slot:extra>
    </x-admin.stats.item>
    <x-admin.stats.item icon="shield-check" :label="__('Plafond par article')" size="sm" data-forecast-limit>
        {{ $forecast['cap'] ?? '–' }}
        <x-slot:extra>
            @if ($forecast['limit'])
                {{ __('Un article ne dépasse jamais :limit, soit ce plafond augmenté de :percent %.', ['limit' => $forecast['limit'], 'percent' => $forecast['percent']]) }}
            @endif
        </x-slot:extra>
    </x-admin.stats.item>
</x-admin.stats>

@if ($forecast['exceeds'])
    <x-ui::alert type="warning" data-forecast-above-cap :title="__('Le coût estimé dépasse le plafond, marge comprise')">
        {{ $advice }}
    </x-ui::alert>
@endif
