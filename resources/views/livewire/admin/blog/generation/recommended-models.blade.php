{{-- Le retour aux modeles recommandes, sous les listes des modeles, au meme endroit sur ordinateur et sur
     telephone. Un simple lien « Réinitialiser », de la forme de « Reprendre l'adresse depuis le titre » de
     l'editeur, et seulement tant qu'un modele differe de nos recommandations : il les remet dans les listes,
     sans rien enregistrer (demandes de Micha du 19/11 et du 20/11). $recommended : les modeles a l'ecran sont
     ceux que nous recommandons. --}}
@unless ($recommended)
    <p class="mt-4 text-[12px]">
        <button type="button" wire:click="restoreRecommendedModels" data-restore-recommended-models
            class="font-medium text-secondary underline underline-offset-2 hover:text-primary">
            {{ __('Réinitialiser') }}
        </button>
    </p>
@endunless
