{{-- Le retour aux modeles recommandes, a droite du titre « Modèles » (sous les listes sur telephone). Tant qu'un
     modele differe de nos recommandations, un bouton les remet dans les listes, sans rien enregistrer ; sinon,
     une mention grise dit qu'elles sont en place (demande de Micha du 19/11). $recommended : les modeles a
     l'ecran sont ceux que nous recommandons. --}}
@if ($recommended)
    <span class="inline-flex items-center gap-1.5 text-[13px] text-muted" data-models-recommended>
        <x-ui::icon name="check" class="h-4 w-4" /> {{ __('Modèles recommandés') }}
    </span>
@else
    <x-ui::button variant="secondary" size="compact" wire:click="restoreRecommendedModels" :loading="true" target="restoreRecommendedModels" data-restore-recommended-models>
        <x-ui::icon name="arrow-uturn-left" /> {{ __('Remettre les modèles recommandés') }}
    </x-ui::button>
@endif
