{{-- La page d'une generation, sur page blanche (decision de Micha du 16/11/2026). De haut en bas :
     1. le resultat : l'etat (un point de couleur et son libelle, sans pastille ronde), ce qui s'est passe en
        grand, ce que la personne peut faire, puis la bande des trois chiffres (cout, duree, tokens) ;
     2. la demande d'origine, dans une seconde bande de meme forme (aucune colonne laterale : trois ou quatre
        lignes ne doivent pas serrer toute la page) ;
     3. le compte rendu de l'IA, puis ses etapes en frise.
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
        <x-ui::load-failure class="mt-6" :title="__('Le chargement de cette génération a échoué.')"
            :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
    @else
        @if ($generation === null)
            <x-ui::empty-state class="mt-6" icon="sparkles" :title="__('Cette génération est introuvable.')"
                :description="__('Elle a peut-être été supprimée.')" />
        @else
            @php
                $running = $generation->runningStep();
                $total = count($generation->steps);
                $status = $generation->status;
                $types = \App\Enums\Blog\Generation\BlogGenerationType::class;
                // Une demande faite depuis un article (image, traduction) n'envoie pas d'e-mail : son resultat est remis
                // dans l'article (Micha, 24/11/2026).
                $isRequest = $generation->type !== $types::Article;

                // Ce que la personne lit en premier : ou en est la generation, en quelques mots.
                $headline = match (true) {
                    $status === $statuses::Queued => __('Génération demandée, démarrage dans quelques secondes.'),
                    $status === $statuses::Running => __('Étape :done sur :total', ['done' => min($total, $generation->completedSteps() + 1), 'total' => $total]).($running ? ', '.mb_strtolower($running->label) : ''),
                    $status === $statuses::Completed && $generation->postId !== null => __('Brouillon prêt à relire'),
                    $status === $statuses::Completed && $generation->type === $types::Image => $generation->obtainedImage !== null ? __('Image obtenue') : __('Aucune image retenue'),
                    $status === $statuses::Completed && $generation->type === $types::Translation => __('Traduction prête'),
                    $status === $statuses::Completed => __('Génération terminée'),
                    $status === $statuses::Cancelled => __('Génération arrêtée manuellement'),
                    default => $generation->outcome ?? $status->label(),
                };

                // La phrase qui suit : ce qui s'est passe et ce que la personne peut faire. Jamais la cause technique.
                $explanation = match (true) {
                    $generation->isActive() && $isRequest => __('Vous pouvez quitter cette page. Le résultat vous sera remis dans l\'article, à votre prochain passage.'),
                    $generation->isActive() => __('Vous pouvez quitter cette page. Un e-mail vous préviendra quand la génération sera terminée.'),
                    $status === $statuses::Completed && $generation->postId !== null => __('L\'article est enregistré en brouillon. Rien n\'est publié sans votre validation.'),
                    $status === $statuses::Completed && $generation->type === $types::Image => $generation->obtainedImage !== null ? null : __('Vous pouvez en demander une autre depuis l\'article.'),
                    $status === $statuses::Completed && $generation->type === $types::Translation => __('Elle remplit les champs de la langue visée dans l\'article. Relisez-la, puis enregistrez l\'article.'),
                    // Mode d'essai (aucun article cree), ou brouillon supprime depuis : on ne promet pas un brouillon qui n'existe pas.
                    $status === $statuses::Completed => __('Aucun brouillon n\'est rattaché à cette génération.'),
                    $status === $statuses::Cancelled => trim(($generation->outcome ?? '').' '.($generation->advice ?? '')),
                    default => $generation->advice,
                };

                // L'etat se dit par un point de couleur et son libelle, sans fond.
                $tone = match ($status) {
                    $statuses::Completed => 'text-emerald-700 dark:text-emerald-400',
                    $statuses::Failed => 'text-red-700 dark:text-red-400',
                    $statuses::Queued, $statuses::Running => 'text-blue-700 dark:text-blue-400',
                    default => 'text-secondary',
                };

                $hasActions = $generation->postId || $generation->sourcePostId || $generation->isRetryable() || $generation->isActive();
                // « La demande » ne montre que ce qui a un sens pour cette sorte de generation (Micha, 24/11/2026) :
                // une traduction n'a ni images ni longueur, une image demandee n'a pas de longueur.
                $showsImages = $generation->type !== $types::Translation;
                $showsLength = $generation->type === $types::Article;
                $reportIcons = ['topic' => 'light-bulb', 'research' => 'magnifying-glass', 'writing' => 'pencil-square'];
            @endphp

            <x-ui::page-header :title="$generation->subject ?? __('Sujet choisi par l\'IA')"
                :description="__('Génération n° :id.', ['id' => $generation->id]).' '.$generation->launched.'.'" class="mb-8 mt-2" />

            {{-- 1. Le resultat : le bloc le plus fort de la page. --}}
            <section data-generation-result="{{ $status->value }}">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0" @if ($generation->outcome) data-generation-outcome @endif>
                        <p class="inline-flex items-center gap-2 text-[12px] font-semibold uppercase tracking-[0.08em] {{ $tone }}" data-generation-state>
                            @if ($status === $statuses::Running)
                                <x-ui::spinner size="xs" />
                            @else
                                <span class="h-2 w-2 rounded-full bg-current" aria-hidden="true"></span>
                            @endif
                            {{ $status->label() }}
                        </p>
                        <p class="mt-2 text-[20px] font-semibold leading-snug tracking-tight text-primary" data-generation-headline @if ($generation->isActive()) data-generation-progress @endif>{{ $headline }}</p>
                        @if (filled($explanation))
                            <p class="mt-2 max-w-3xl text-[14px] leading-relaxed text-secondary">{{ $explanation }}</p>
                        @endif
                        @if ($generation->isActive())
                            <x-ui::progress class="mt-4 max-w-xl" :value="$generation->percent()" :show-value="false" />
                        @endif
                    </div>

                    @if ($hasActions)
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @if ($generation->postId)
                                <x-ui::button :href="route('admin.blog.posts.edit', ['post' => $generation->postId])">
                                    <x-ui::icon name="document-text" /> {{ __('Ouvrir le brouillon') }}
                                </x-ui::button>
                            @elseif ($generation->sourcePostId)
                                <x-ui::button :href="route('admin.blog.posts.edit', ['post' => $generation->sourcePostId])" data-open-source-post>
                                    <x-ui::icon name="document-text" /> {{ __('Ouvrir l\'article') }}
                                </x-ui::button>
                            @endif

                            @if ($generation->isRetryable())
                                <x-ui::button wire:click="retry" :loading="true" target="retry" data-relaunch-generation><x-ui::icon name="arrow-path" /> {{ $generation->relaunchLabel() }}</x-ui::button>
                            @elseif ($generation->isActive())
                                <x-ui::button variant="secondary" data-stop-generation x-on:click="$dispatch('ui-open-modal', 'stop-generation')"><x-ui::icon name="stop" /> {{ __('Arrêter') }}</x-ui::button>
                            @endif
                        </div>
                    @endif
                </div>

                <x-admin.stats class="mt-7" :columns="3" data-generation-facts>
                    <x-admin.stats.item icon="currency-dollar" :label="__('Coût')">
                        <span data-generation-cost>{{ $generation->cost }}</span>
                        @if ($generation->announced)
                            <x-slot:extra><span data-generation-announced>{{ $generation->announced }}</span></x-slot:extra>
                        @endif
                    </x-admin.stats.item>
                    <x-admin.stats.item icon="clock" :label="$generation->isActive() ? __('En cours depuis') : __('Durée')">{{ $generation->duration ?? '–' }}</x-admin.stats.item>
                    <x-admin.stats.item icon="circle-stack" :label="__('Tokens')">
                        <x-admin.blog.tokens class="text-[22px] leading-none" :input="$generation->inputTokens" :output="$generation->outputTokens" />
                    </x-admin.stats.item>
                </x-admin.stats>
            </section>

            {{-- 2. La demande d'origine : la meme forme, en plus discret (valeurs en 14 px). Le filet du titre de
                 section sert de bord haut a la bande. --}}
            <x-admin.section :title="__('La demande')" data-generation-request>
                <x-admin.stats class="border-t-0" :columns="3 + (int) $showsImages + (int) $showsLength">
                    <x-admin.stats.item icon="bolt" :label="__('Lancement')" size="sm">{{ $generation->trigger->label() }}</x-admin.stats.item>
                    <x-admin.stats.item icon="chat-bubble-left-ellipsis" :label="__('Sujet demandé')" size="sm">
                        @if ($generation->topicHint)
                            {{ $generation->topicHint }}
                        @else
                            <span class="font-normal text-secondary">{{ __('Laissé au choix de l\'IA') }}</span>
                        @endif
                    </x-admin.stats.item>
                    @if ($showsImages)
                        <x-admin.stats.item icon="photo" :label="__('Images')" size="sm">{{ $generation->images ?? '–' }}</x-admin.stats.item>
                    @endif
                    @if ($showsLength)
                        <x-admin.stats.item icon="document-text" :label="__('Longueur demandée')" size="sm">{{ $generation->length ?? '–' }}</x-admin.stats.item>
                    @endif
                    <x-admin.stats.item icon="adjustments-horizontal" :label="__('Effort')" size="sm" data-generation-effort>{{ $generation->effort ?? '–' }}</x-admin.stats.item>
                </x-admin.stats>

                @if ($generation->instructions)
                    <div class="border-b border-default py-5">
                        <p class="flex items-center gap-1.5 text-[12px] font-medium text-muted"><x-ui::icon name="pencil-square" class="h-4 w-4" /> {{ __('Consignes') }}</p>
                        <p class="mt-2 max-w-4xl whitespace-pre-line text-[14px] leading-relaxed text-primary">{{ $generation->instructions }}</p>
                    </div>
                @endif
            </x-admin.section>

            {{-- L'image qu'une demande d'image a obtenue (Micha, 24/11/2026), que l'article a gardee ou non. --}}
            @if ($generation->obtainedImage !== null)
                <x-admin.section :title="__('L\'image obtenue')" :description="$generation->sourcePostId ? __('Vous la retrouvez dans « Images déjà obtenues », sous l\'image principale de l\'article.') : null" data-obtained-image>
                    <figure class="max-w-md">
                        <img src="{{ $generation->obtainedImage['url'] }}" alt="{{ $generation->obtainedImage['alt'] }}" class="aspect-video w-full rounded-lg bg-elevated object-cover">
                        <figcaption class="mt-2 text-[13px] leading-relaxed text-secondary">{{ $generation->obtainedImage['origin'] }}. {{ $generation->obtainedImage['alt'] }}</figcaption>
                    </figure>
                </x-admin.section>
            @endif

            {{-- Les rares affirmations gardees sur lesquelles la verification a un doute (decision de Micha du 19/11).
                 Ce qu'elle a retire ou corrige ne s'affiche plus : c'est regle. Chaque point dit ou le trouver dans le
                 brouillon francais, ce que dit l'article, et quoi verifier, pour eviter les allers-retours. La
                 plupart des generations n'en ont aucun, et la section n'apparait pas. --}}
            @if ($generation->attentionPoints !== [])
                <x-admin.section :title="__('Points d\'attention')" :description="__('Des affirmations gardées dans l\'article, sur lesquelles la vérification a un doute. Vérifiez-les avant de publier.')" data-attention-points>
                    <div class="divide-y divide-[var(--ui-border)]">
                        @foreach ($generation->attentionPoints as $point)
                            <section class="py-5" data-attention-point>
                                @if ($point['where'])
                                    <p class="text-[12px] text-muted">{{ $point['where'] }}</p>
                                @endif
                                <p class="mt-1 max-w-3xl text-[14px] leading-[1.7] text-primary">{{ $point['statement'] }}</p>
                                <p class="mt-1 max-w-3xl text-[13px] leading-[1.6] text-secondary">{{ $point['check'] }}</p>
                            </section>
                        @endforeach
                    </div>
                </x-admin.section>
            @endif

            {{-- 3. Ce que l'IA a fait. --}}
            @if ($generation->report !== [])
                <x-admin.section :title="__('Compte rendu de l\'IA')" :description="__('Les choix faits par l\'IA pour cet article, expliqués par elle.')" data-generation-report>
                    <div class="divide-y divide-[var(--ui-border)]">
                        @foreach ($generation->report as $point)
                            <section class="flex gap-4 py-5">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-elevated text-secondary" aria-hidden="true">
                                    <x-ui::icon :name="$reportIcons[$point['key']] ?? 'chat-bubble-left-ellipsis'" class="h-[18px] w-[18px]" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-[15px] font-semibold leading-snug text-primary">{{ $point['label'] }}</h3>
                                    <p class="mt-1.5 max-w-3xl text-[14px] leading-[1.7] text-secondary">{{ $point['text'] }}</p>
                                </div>
                            </section>
                        @endforeach
                    </div>
                </x-admin.section>
            @endif

            <x-admin.section :title="__('Étapes')">
                <x-slot:actions>
                    {{ trans_choice('{0} Aucune étape terminée|{1} :count étape terminée sur :total|[2,*] :count étapes terminées sur :total', $generation->completedSteps(), ['count' => $generation->completedSteps(), 'total' => $total]) }}
                </x-slot:actions>

                @include('livewire.admin.blog.generation.steps', ['steps' => $generation->steps])
            </x-admin.section>

            <x-ui::modal name="stop-generation" variant="confirm" :title="__('Arrêter cette génération ?')">
                <p>{{ __('La génération s\'arrête tout de suite. Si une étape est en cours, son appel à l\'IA est déjà parti et sera facturé, mais son résultat ne sera pas gardé. Ce qui a déjà été dépensé reste dépensé.') }}</p>

                <x-slot:actions>
                    <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'stop-generation')">{{ __('Continuer la génération') }}</x-ui::button>
                    <x-ui::button variant="danger" x-on:click="$wire.cancel(); $dispatch('ui-close-modal', 'stop-generation')">{{ __('Arrêter') }}</x-ui::button>
                </x-slot:actions>
            </x-ui::modal>
        @endif
    @enderror
</div>
