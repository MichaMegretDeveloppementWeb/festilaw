{{-- La page d'une generation : ce qui s'est passe, ce qu'elle a consomme (une grille de donnees, pas une
     liste d'une ligne par donnee), le compte rendu de l'IA, puis ses etapes. On y arrive des le lancement :
     l'ecran se relit toutes les 2 secondes tant que la generation n'est pas terminee. --}}
<div data-generation-detail="{{ $generation?->status->value ?? 'missing' }}">
    @if ($generation?->isActive())
        <div wire:poll.2s wire:key="generation-detail-poll" class="hidden"></div>
    @endif

    <a href="{{ route('admin.blog.generation.history') }}" class="inline-flex items-center gap-1.5 text-[13px] text-muted transition-colors hover:text-primary">
        <x-ui::icon name="arrow-left" class="h-3.5 w-3.5" /> {{ __('Historique') }}
    </a>

    @error('generation-load-failed')
        <x-ui::card :padding="false" class="mt-4">
            <x-ui::load-failure :title="__('Le chargement de cette génération a échoué.')"
                :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
        </x-ui::card>
    @else
        @if ($generation === null)
            <x-ui::card :padding="false" class="mt-4">
                <x-ui::empty-state icon="sparkles" :title="__('Cette génération est introuvable.')"
                    :description="__('Elle a peut-être été supprimée.')" />
            </x-ui::card>
        @else
            @php
                $running = $generation->runningStep();
                $total = count($generation->steps);
            @endphp

            <x-ui::page-header :title="$generation->subject ?? __('Sujet choisi par l\'IA')"
                :description="__('Génération n° :id.', ['id' => $generation->id]).' '.$generation->launched.'.'" class="mb-6 mt-2">
                <x-admin.blog.generation-status :status="$generation->status" />

                @if ($generation->postId)
                    <x-ui::button :href="route('admin.blog.posts.edit', ['post' => $generation->postId])">{{ __('Ouvrir le brouillon') }}</x-ui::button>
                @endif

                @if ($generation->isRetryable())
                    <x-ui::button wire:click="retry" :loading="true" target="retry"><x-ui::icon name="arrow-path" /> {{ __('Relancer') }}</x-ui::button>
                @elseif ($generation->isActive() && ! $generation->cancelRequested)
                    <x-ui::button variant="secondary" data-stop-generation x-on:click="$dispatch('ui-open-modal', 'stop-generation')"><x-ui::icon name="stop" /> {{ __('Arrêter') }}</x-ui::button>
                @endif
            </x-ui::page-header>

            @if ($generation->cancelRequested && $generation->isActive())
                <x-ui::alert type="warning" class="mb-6" :title="__('Arrêt demandé')">{{ __('La génération s\'arrêtera après l\'étape en cours.') }}</x-ui::alert>
            @endif

            {{-- Ce qui s'est passe et quoi faire, en clair. La cause technique d'un echec ne s'affiche pas : elle est au journal du serveur. --}}
            @if ($generation->outcome)
                <x-ui::alert :type="$generation->isRetryable() ? 'error' : 'info'" class="mb-6" :title="$generation->outcome" data-generation-outcome>{{ $generation->advice }}</x-ui::alert>
            @endif

            <x-ui::card class="mb-6" data-generation-facts>
                @if ($generation->isActive())
                    <div class="mb-5">
                        <p class="text-[13px] font-medium text-primary" data-generation-progress>
                            @if ($generation->status === \App\Enums\Blog\Generation\BlogGenerationStatus::Queued)
                                {{ __('Génération demandée, démarrage dans quelques secondes.') }}
                            @else
                                {{ __('Étape :done sur :total', ['done' => min($total, $generation->completedSteps() + 1), 'total' => $total]) }}@if ($running), {{ mb_strtolower($running->label) }}@endif
                            @endif
                        </p>
                        <x-ui::progress class="mt-2" :value="$generation->percent()" :show-value="false" />
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3 lg:grid-cols-5">
                    <x-admin.fact icon="bolt" :label="__('Lancement')">{{ $generation->trigger->label() }}</x-admin.fact>
                    <x-admin.fact icon="clock" :label="$generation->isActive() ? __('En cours depuis') : __('Durée')">{{ $generation->duration ?? '–' }}</x-admin.fact>
                    <x-admin.fact icon="currency-dollar" :label="__('Coût')"><span data-generation-cost>{{ $generation->cost }}</span></x-admin.fact>
                    <x-admin.fact icon="circle-stack" :label="__('Tokens')">
                        <x-admin.blog.tokens :input="$generation->inputTokens" :output="$generation->outputTokens" />
                    </x-admin.fact>
                    <x-admin.fact icon="photo" :label="__('Images')">{{ $generation->images ?? '–' }}</x-admin.fact>
                </div>

                @if ($generation->topicHint || $generation->instructions)
                    <div class="mt-5 grid gap-5 border-t border-subtle pt-5 sm:grid-cols-2">
                        @if ($generation->topicHint)
                            <x-admin.fact :label="__('Sujet demandé')"><span class="font-normal">{{ $generation->topicHint }}</span></x-admin.fact>
                        @endif
                        @if ($generation->instructions)
                            <x-admin.fact :label="__('Consignes')"><span class="whitespace-pre-line font-normal">{{ $generation->instructions }}</span></x-admin.fact>
                        @endif
                    </div>
                @endif
            </x-ui::card>

            @if ($generation->report !== [])
                <x-admin.panel class="mb-6" :title="__('Compte rendu de l\'IA')" data-generation-report>
                    <dl class="grid gap-x-8 gap-y-5 p-5 md:grid-cols-3">
                        @foreach ($generation->report as $point)
                            <div>
                                <dt class="text-[12px] font-medium text-muted">{{ $point['label'] }}</dt>
                                <dd class="mt-1 text-[13px] leading-relaxed text-primary">{{ $point['text'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-admin.panel>
            @endif

            <x-admin.panel :title="__('Étapes')"
                :description="trans_choice('{0} Aucune étape terminée|{1} :count étape terminée sur :total|[2,*] :count étapes terminées sur :total', $generation->completedSteps(), ['count' => $generation->completedSteps(), 'total' => $total])">
                @include('livewire.admin.blog.generation.steps', ['steps' => $generation->steps])
            </x-admin.panel>

            <x-ui::modal name="stop-generation" variant="confirm" :title="__('Arrêter cette génération ?')">
                <p>{{ __('Une génération en attente est arrêtée tout de suite. Une génération déjà commencée finit l\'étape en cours, puis s\'arrête. Ce qui a déjà été dépensé reste dépensé.') }}</p>

                <x-slot:actions>
                    <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'stop-generation')">{{ __('Continuer la génération') }}</x-ui::button>
                    <x-ui::button variant="danger" x-on:click="$wire.cancel(); $dispatch('ui-close-modal', 'stop-generation')">{{ __('Arrêter') }}</x-ui::button>
                </x-slot:actions>
            </x-ui::modal>
        @endif
    @enderror
</div>
