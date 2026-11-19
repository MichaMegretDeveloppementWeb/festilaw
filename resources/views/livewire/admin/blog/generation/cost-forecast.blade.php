{{-- Fourchette de cout d'un article, partagee par les reglages et la fenetre de lancement. Elle annonce ce que
     l'article coutera et ne bloque rien ; seul l'arret de securite, au double de son haut, est garanti
     (decision de Micha du 18/11). --}}
<x-admin.stats :columns="2" data-forecast-range>
    <x-admin.stats.item icon="calculator" :label="__('Coût estimé d\'un article')" size="sm">
        {{ __('Entre :low et :high', ['low' => $forecast['low'], 'high' => $forecast['high']]) }}
        <x-slot:extra>{{ __('Estimation tirée des articles déjà générés. Le coût réel peut en sortir.') }}</x-slot:extra>
    </x-admin.stats.item>
    <x-admin.stats.item icon="shield-check" :label="__('Arrêt de sécurité')" size="sm" data-forecast-stop>
        {{ $forecast['stop'] }}
        <x-slot:extra>{{ __('La génération s\'arrête d\'elle-même si son coût allait dépasser ce montant.') }}</x-slot:extra>
    </x-admin.stats.item>
</x-admin.stats>
