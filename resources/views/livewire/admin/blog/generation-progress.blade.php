{{-- Carte de suivi en direct de la generation en cours. Elle se relit seule : toutes les 2 secondes tant
     qu'une generation tourne, toutes les 15 secondes sinon (une generation planifiee peut demarrer).
     Deux balises distinctes, pour que le rythme change vraiment quand l'etat change. --}}
<div data-generation-progress="{{ $generation === null ? 'idle' : $generation->status->value }}">
    @if ($generation?->isActive())
        <div wire:poll.2s wire:key="generation-poll-active" class="hidden"></div>
    @else
        <div wire:poll.15s wire:key="generation-poll-idle" class="hidden"></div>
    @endif

    @error('progress-load-failed')
        <x-ui::card class="mb-6">
            <x-ui::load-failure :title="$message" :compact="true" />
        </x-ui::card>
    @else
        @if ($generation !== null)
            @php
                $queued = $generation->status === \App\Enums\Blog\Generation\BlogGenerationStatus::Queued;
                $running = $generation->runningStep();
            @endphp
            <x-admin.panel class="mb-6" :title="$queued ? __('Génération demandée') : __('Génération en cours')">
                <x-slot:actions>
                    <div class="flex shrink-0 items-center gap-2">
                        <x-ui::button variant="ghost" size="compact" :href="route('admin.blog.generation.show', ['generation' => $generation->id])">{{ __('Détail') }}</x-ui::button>
                        @if ($generation->cancelRequested)
                            <x-ui::badge color="amber">{{ __('Arrêt demandé') }}</x-ui::badge>
                        @else
                            <x-ui::button variant="secondary" size="compact" data-stop-generation
                                x-on:click="$dispatch('generation-stop-asked', { id: {{ $generation->id }} })">
                                <x-ui::icon name="stop" /> {{ __('Arrêter') }}
                            </x-ui::button>
                        @endif
                    </div>
                </x-slot:actions>

                <div class="border-b border-subtle px-5 py-4">
                    <p class="text-[13px] font-medium text-primary">{{ $generation->subject ?? __('Sujet choisi par l\'IA') }}</p>

                    @if ($queued)
                        <p class="mt-2 flex items-center gap-2 text-[13px] text-muted" data-generation-starting>
                            <x-ui::spinner size="xs" /> {{ __('Génération demandée, démarrage…') }}
                        </p>
                    @else
                        <p class="mt-1 text-[12px] text-muted">
                            {{ __('Étape :done sur :total', ['done' => min(count($generation->steps), $generation->completedSteps() + 1), 'total' => count($generation->steps)]) }}
                            @if ($running)
                                · {{ $running->label }}
                            @endif
                            · {{ __('depuis :duration', ['duration' => $generation->duration]) }}
                            · {{ $generation->cost }}
                        </p>
                        <x-ui::progress class="mt-3" :value="$generation->percent()" :show-value="false" />
                    @endif
                </div>

                @include('livewire.admin.blog.generation.steps', ['steps' => $generation->steps, 'detailed' => false])
            </x-admin.panel>
        @endif
    @enderror
</div>
