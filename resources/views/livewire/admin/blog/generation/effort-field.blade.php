{{-- Le niveau d'effort, le meme dans les reglages et dans la fenetre de lancement (demande de Micha du 19/11).
     L'aide dit ce que change le niveau, sans chiffre : le cout estime, juste en dessous, suit le choix.
     $id : l'identifiant du champ. La portee porte `effort` et `$effortLevels`. --}}
<x-ui::form-group :label="__('Niveau d\'effort')" :for="$id" :error="$errors->first('effort')"
    :hint="__('Élevé lit plus de sources et réfléchit davantage, pour un coût plus élevé. Faible lit moins de sources, pour un coût plus bas.')">
    <x-ui::select :id="$id" wire:model.live="effort" :options="$effortLevels" :error="$errors->has('effort')" data-effort-field />
</x-ui::form-group>
