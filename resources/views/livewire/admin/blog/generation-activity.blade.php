{{-- Tuile « En cours » de la vue d'ensemble. Elle se relit seule : toutes les 2 secondes tant qu'une
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
<div class="h-full" data-generation-activity="{{ $generation === null ? 'idle' : $generation->status->value }}">
    @if ($generation?->isActive())
        <div wire:poll.2s wire:key="generation-activity-active" class="hidden"></div>
    @else
        <div wire:poll.15s wire:key="generation-activity-idle" class="hidden"></div>
    @endif

    <x-ui::stat-card class="h-full" icon="bolt" :label="__('En cours')" data-stat="activity" :value="$value" :description="$line">
        <x-ui::progress class="mt-3" :value="$generation?->percent() ?? 0" :show-value="false" />
        <a href="{{ $generation === null ? '#' : route('admin.blog.generation.show', ['generation' => $generation->id]) }}"
            @if ($generation === null) tabindex="-1" aria-hidden="true" @endif data-follow-generation
            @class(['mt-3 inline-flex items-center gap-1 text-[12px] font-medium text-secondary underline underline-offset-2 hover:text-primary', 'invisible' => $generation === null])>{{ __('Suivre la génération') }}</a>
    </x-ui::stat-card>
</div>
