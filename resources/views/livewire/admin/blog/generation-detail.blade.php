{{-- La page d'une generation. Trois niveaux de lecture, du plus fort au plus discret :
     1. le resultat, en tete : ou en est la generation, ce que la personne peut faire, puis ce qu'elle a coute,
        en grands chiffres ;
     2. ce que l'IA a fait : son compte rendu en trois points, puis ses etapes en frise ;
     3. la demande d'origine, dans une colonne discrete a droite (sous le reste sur un petit ecran).
     On y arrive des le lancement : l'ecran se relit toutes les 2 secondes tant que la generation n'est pas terminee. --}}
@php
    $statuses = \App\Enums\Blog\Generation\BlogGenerationStatus::class;
@endphp
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
                $status = $generation->status;

                // Ce que la personne lit en premier : ou en est la generation, en quelques mots.
                $headline = match (true) {
                    $status === $statuses::Queued => __('Génération demandée, démarrage dans quelques secondes.'),
                    $status === $statuses::Running => __('Étape :done sur :total', ['done' => min($total, $generation->completedSteps() + 1), 'total' => $total]).($running ? ', '.mb_strtolower($running->label) : ''),
                    $status === $statuses::Completed && $generation->postId !== null => __('Brouillon prêt à relire'),
                    $status === $statuses::Completed => __('Génération terminée'),
                    $status === $statuses::Cancelled => __('Génération arrêtée'),
                    default => $generation->outcome ?? $status->label(),
                };

                // La phrase qui suit : ce qui s'est passe et ce que la personne peut faire. Jamais la cause technique.
                $explanation = match (true) {
                    $generation->isActive() && $generation->cancelRequested => __('Arrêt demandé. La génération s\'arrêtera après l\'étape en cours.'),
                    $generation->isActive() => __('Vous pouvez quitter cette page. Un e-mail vous préviendra quand la génération sera terminée.'),
                    $status === $statuses::Completed && $generation->postId !== null => __('L\'article est enregistré en brouillon. Rien n\'est publié sans votre validation.'),
                    // Mode d'essai (aucun article cree), ou brouillon supprime depuis : on ne promet pas un brouillon qui n'existe pas.
                    $status === $statuses::Completed => __('Aucun brouillon n\'est rattaché à cette génération.'),
                    $status === $statuses::Cancelled => trim(($generation->outcome ?? '').' '.($generation->advice ?? '')),
                    default => $generation->advice,
                };

                $tone = match ($status) {
                    $statuses::Completed => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
                    $statuses::Failed => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400',
                    $statuses::Queued, $statuses::Running => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400',
                    default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                };

                $reportIcons = ['topic' => 'light-bulb', 'research' => 'magnifying-glass', 'writing' => 'pencil-square'];
            @endphp

            <x-ui::page-header :title="$generation->subject ?? __('Sujet choisi par l\'IA')"
                :description="__('Génération n° :id.', ['id' => $generation->id]).' '.$generation->launched.'.'" class="mb-6 mt-2" />

            {{-- 1. Le resultat. Un seul bloc, le plus fort de la page : l'etat, ce qu'on peut faire, puis les chiffres. --}}
            <x-ui::card :padding="false" class="mb-6" data-generation-result="{{ $status->value }}">
                <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-start">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $tone }}" aria-hidden="true">
                        @if ($status === $statuses::Running)
                            <x-ui::spinner size="sm" />
                        @else
                            <x-ui::icon class="h-6 w-6" :name="match ($status) {
                                $statuses::Queued => 'clock',
                                $statuses::Completed => 'check',
                                $statuses::Failed => 'exclamation-triangle',
                                $statuses::Cancelled => 'stop',
                                default => 'no-symbol',
                            }" />
                        @endif
                    </span>

                    <div class="min-w-0 flex-1" @if ($generation->outcome) data-generation-outcome @endif>
                        <p class="text-[12px] font-semibold uppercase tracking-[0.08em] text-muted">{{ $status->label() }}</p>
                        <p class="mt-1 text-[20px] font-semibold leading-snug tracking-tight text-primary" @if ($generation->isActive()) data-generation-progress @endif>{{ $headline }}</p>
                        @if (filled($explanation))
                            <p class="mt-2 max-w-3xl text-[14px] leading-relaxed text-secondary">{{ $explanation }}</p>
                        @endif

                        @if ($generation->isActive())
                            <x-ui::progress class="mt-4 max-w-xl" :value="$generation->percent()" :show-value="false" />
                        @endif
                    </div>

                    {{-- Rien a faire sur cette generation : pas de zone vide a la place des boutons. --}}
                    @if ($generation->postId || $generation->isRetryable() || ($generation->isActive() && ! $generation->cancelRequested))
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        @if ($generation->postId)
                            <x-ui::button :href="route('admin.blog.posts.edit', ['post' => $generation->postId])">
                                <x-ui::icon name="document-text" /> {{ __('Ouvrir le brouillon') }}
                            </x-ui::button>
                        @endif

                        @if ($generation->isRetryable())
                            <x-ui::button wire:click="retry" :loading="true" target="retry"><x-ui::icon name="arrow-path" /> {{ __('Relancer') }}</x-ui::button>
                        @elseif ($generation->isActive() && ! $generation->cancelRequested)
                            <x-ui::button variant="secondary" data-stop-generation x-on:click="$dispatch('ui-open-modal', 'stop-generation')"><x-ui::icon name="stop" /> {{ __('Arrêter') }}</x-ui::button>
                        @endif
                    </div>
                    @endif
                </div>

                {{-- Les trois chiffres de la generation, en grand. Ce qui decrit la demande est dans la colonne de droite. --}}
                <dl class="grid grid-cols-1 divide-y divide-subtle border-t border-subtle sm:grid-cols-3 sm:divide-x sm:divide-y-0" data-generation-facts>
                    <div class="px-6 py-5">
                        <dt class="flex items-center gap-1.5 text-[12px] font-medium text-muted"><x-ui::icon name="currency-dollar" class="h-4 w-4" /> {{ __('Coût') }}</dt>
                        <dd class="mt-1.5 text-[26px] font-semibold leading-none tracking-tight tabular-nums text-primary" data-generation-cost>{{ $generation->cost }}</dd>
                    </div>
                    <div class="px-6 py-5">
                        <dt class="flex items-center gap-1.5 text-[12px] font-medium text-muted"><x-ui::icon name="clock" class="h-4 w-4" /> {{ $generation->isActive() ? __('En cours depuis') : __('Durée') }}</dt>
                        <dd class="mt-1.5 text-[26px] font-semibold leading-none tracking-tight tabular-nums text-primary">{{ $generation->duration ?? '–' }}</dd>
                    </div>
                    <div class="px-6 py-5">
                        <dt class="flex items-center gap-1.5 text-[12px] font-medium text-muted"><x-ui::icon name="circle-stack" class="h-4 w-4" /> {{ __('Tokens') }}</dt>
                        <dd class="mt-1.5 text-[20px] font-semibold leading-[26px] tracking-tight text-primary">
                            <x-admin.blog.tokens :input="$generation->inputTokens" :output="$generation->outputTokens" />
                        </dd>
                    </div>
                </dl>
            </x-ui::card>

            <div class="grid items-start gap-x-8 gap-y-6 lg:grid-cols-[minmax(0,1fr)_17rem] xl:grid-cols-[minmax(0,1fr)_20rem]">
                {{-- 2. Ce que l'IA a fait. --}}
                <div class="min-w-0 space-y-6">
                    @if ($generation->report !== [])
                        <x-admin.panel :title="__('Compte rendu de l\'IA')" :description="__('Les choix faits par l\'IA pour cet article, expliqués par elle.')" data-generation-report>
                            <div class="divide-y divide-subtle">
                                @foreach ($generation->report as $point)
                                    <section class="flex gap-4 px-5 py-5">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-elevated text-secondary" aria-hidden="true">
                                            <x-ui::icon :name="$reportIcons[$point['key']] ?? 'chat-bubble-left-ellipsis'" class="h-[18px] w-[18px]" />
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="text-[15px] font-semibold leading-snug text-primary">{{ $point['label'] }}</h3>
                                            <p class="mt-1.5 max-w-prose text-[14px] leading-[1.7] text-secondary">{{ $point['text'] }}</p>
                                        </div>
                                    </section>
                                @endforeach
                            </div>
                        </x-admin.panel>
                    @endif

                    <x-admin.panel :title="__('Étapes')"
                        :description="trans_choice('{0} Aucune étape terminée|{1} :count étape terminée sur :total|[2,*] :count étapes terminées sur :total', $generation->completedSteps(), ['count' => $generation->completedSteps(), 'total' => $total])">
                        @include('livewire.admin.blog.generation.steps', ['steps' => $generation->steps])
                    </x-admin.panel>
                </div>

                {{-- 3. La demande d'origine : une colonne discrete, sans carte, pour ne pas concurrencer le reste. --}}
                <aside class="min-w-0 lg:pt-1" data-generation-request>
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.08em] text-muted">{{ __('La demande') }}</h2>

                    <dl class="mt-4 space-y-5">
                        <div class="flex gap-3">
                            <x-ui::icon name="bolt" class="mt-0.5 h-4 w-4 shrink-0 text-muted" />
                            <div class="min-w-0">
                                <dt class="text-[12px] text-muted">{{ __('Lancement') }}</dt>
                                <dd class="mt-0.5 text-[14px] font-medium text-primary">{{ $generation->trigger->label() }}</dd>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <x-ui::icon name="chat-bubble-left-ellipsis" class="mt-0.5 h-4 w-4 shrink-0 text-muted" />
                            <div class="min-w-0">
                                <dt class="text-[12px] text-muted">{{ __('Sujet demandé') }}</dt>
                                <dd @class(['mt-0.5 text-[14px]', 'font-medium text-primary' => $generation->topicHint, 'text-secondary' => ! $generation->topicHint])>{{ $generation->topicHint ?? __('Laissé au choix de l\'IA') }}</dd>
                            </div>
                        </div>

                        @if ($generation->instructions)
                            <div class="flex gap-3">
                                <x-ui::icon name="pencil-square" class="mt-0.5 h-4 w-4 shrink-0 text-muted" />
                                <div class="min-w-0">
                                    <dt class="text-[12px] text-muted">{{ __('Consignes') }}</dt>
                                    <dd class="mt-0.5 whitespace-pre-line text-[14px] leading-relaxed text-primary">{{ $generation->instructions }}</dd>
                                </div>
                            </div>
                        @endif

                        @if ($generation->images)
                            <div class="flex gap-3">
                                <x-ui::icon name="photo" class="mt-0.5 h-4 w-4 shrink-0 text-muted" />
                                <div class="min-w-0">
                                    <dt class="text-[12px] text-muted">{{ __('Images') }}</dt>
                                    <dd class="mt-0.5 text-[14px] font-medium text-primary">{{ $generation->images }}</dd>
                                </div>
                            </div>
                        @endif
                    </dl>
                </aside>
            </div>

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
