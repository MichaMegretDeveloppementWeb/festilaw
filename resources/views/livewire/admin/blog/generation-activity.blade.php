{{-- Cellule « En cours » de la page d'arrivee. Elle se relit seule : toutes les 2 secondes tant qu'une
     generation tourne, toutes les 15 secondes sinon (une generation planifiee peut demarrer). Deux balises
     distinctes, pour que le rythme change vraiment quand l'etat change.
     Sa structure est la meme dans tous les etats (valeur, une ligne, barre, lien) : sa taille ne bouge pas. --}}
@php
    $queued = $generation?->status === \App\Enums\Blog\Generation\BlogGenerationStatus::Queued;
    $running = $generation?->runningStep();
    $failed = $errors->has('activity-load-failed');
    $value = match (true) {
        $failed => '–',
        $generation === null => __('Aucune'),
        $queued => __('En attente'),
        default => __('Étape :done sur :total', ['done' => min(count($generation->steps), $generation->completedSteps() + 1), 'total' => count($generation->steps)]),
    };
    $line = match (true) {
        $failed => $errors->first('activity-load-failed'),
        $generation === null => __('Aucune génération en attente ni en cours'),
        $queued => __('Démarrage dans quelques secondes'),
        $running !== null => $running->label,
        default => __('Étape suivante en préparation'),
    };
@endphp
{{-- La cellule est la racine du composant : elle prend place dans la bande de chiffres de la page d'arrivee. --}}
<x-admin.stats.item icon="bolt" :label="__('En cours')" data-stat="activity" data-generation-activity="{{ $generation === null ? 'idle' : $generation->status->value }}">
    {{ $value }}
    <x-slot:extra>
        @if ($generation?->isActive())
            <div wire:poll.2s wire:key="generation-activity-active" class="hidden"></div>
        @else
            <div wire:poll.15s wire:key="generation-activity-idle" class="hidden"></div>
        @endif

        <span class="block truncate">{{ $line }}</span>
        <x-ui::progress class="mt-2.5" :value="$generation?->percent() ?? 0" :show-value="false" />
        <a href="{{ $generation === null ? '#' : route('admin.blog.generation.show', ['generation' => $generation->id]) }}"
            @if ($generation === null) tabindex="-1" aria-hidden="true" @endif data-follow-generation
            @class(['mt-2.5 inline-flex items-center gap-1 font-medium text-secondary transition-colors hover:text-primary', 'invisible' => $generation === null])>{{ __('Suivre la génération') }} <x-ui::icon name="arrow-right" class="h-3.5 w-3.5" /></a>
    </x-slot:extra>
</x-admin.stats.item>
