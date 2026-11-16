{{-- Page d'arrivee de la generation par IA : quatre tuiles (depense, automatique, brouillons a relire, en
     cours), les dernieres generations, puis l'etat du service. Le lancement est une fenetre de l'en-tete ;
     l'historique s'ouvre par son lien, les reglages par la roue crantee de l'en-tete. Rien n'apparait ni ne pousse la page : la tuile « En cours »
     garde sa taille, qu'une generation tourne ou non. --}}
<div>
    @include('livewire.admin.blog.generation.header', ['page' => 'index'])

    @error('generation-load-failed')
        <x-ui::card :padding="false">
            <x-ui::load-failure :title="__('Le chargement de la génération par IA a échoué.')"
                :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
        </x-ui::card>
    @else
        {{-- Une alerte seulement quand quelque chose empeche de generer, avec ce que la personne peut faire. --}}
        @if ($overview->unavailableReason)
            <x-ui::alert type="error" class="mb-6" :title="__('La génération n\'est pas disponible')">{{ $overview->unavailableReason }} {{ __('Contactez l\'assistance technique.') }}</x-ui::alert>
        @elseif (! $overview->simulated && ! $overview->apiKeyPresent)
            <x-ui::alert type="warning" class="mb-6" :title="__('La génération n\'est pas disponible pour le moment')">{{ __('Contactez l\'assistance technique.') }}</x-ui::alert>
        @endif

        @unless ($overview->workerListening)
            {{-- Dit avec les mots de la personne qui ecrit des articles : ce qu'elle constate, ce que cela change pour elle, quoi faire. --}}
            <x-ui::alert type="warning" class="mb-6" :title="__('Les générations ne démarrent pas pour le moment')">{{ __('Un article demandé maintenant restera en attente. Si cela dure, contactez l\'assistance technique.') }}</x-ui::alert>
        @endunless

        @if ($overview->capReached)
            <x-ui::alert type="warning" class="mb-6" :title="__('Le plafond de dépense du mois est atteint')">{{ __('Aucune génération ne se lance jusqu\'au mois prochain. Le plafond se relève dans les réglages.') }}</x-ui::alert>
        @endif

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui::stat-card class="h-full" icon="currency-dollar" :label="__('Dépense du mois')" :value="$overview->spent" data-stat="spend"
                :description="__('sur :cap de plafond', ['cap' => $overview->cap])">
                <x-ui::progress class="mt-3" :value="$overview->spentPercent" :color="$overview->capReached ? 'red' : 'indigo'" :show-value="false" />
            </x-ui::stat-card>

            <x-ui::stat-card class="h-full" icon="clock" :label="__('Génération automatique')" data-stat="automatic"
                :value="$overview->settings->enabled ? __('Activée') : __('Désactivée')" :description="$overview->automaticRhythm" />

            <x-ui::stat-card class="h-full" icon="document-text" :label="__('Brouillons à relire')" data-stat="drafts" :value="(string) $overview->pendingDrafts"
                :description="trans_choice('{0} Aucun brouillon généré n\'attend de relecture|{1} brouillon généré attend votre relecture|[2,*] brouillons générés attendent votre relecture', $overview->pendingDrafts)">
                @if ($overview->pendingDrafts > 0)
                    <a href="{{ route('admin.blog.posts.index', ['origin' => 'ai', 'status' => 'draft']) }}"
                        class="mt-3 inline-flex items-center gap-1 text-[12px] font-medium text-secondary underline underline-offset-2 hover:text-primary">{{ __('Voir les brouillons') }}</a>
                @endif
            </x-ui::stat-card>

            <livewire:admin.blog.generation-activity />
        </div>

        <x-admin.panel class="mb-6" :title="__('Dernières générations')">
            <x-slot:actions>
                <a href="{{ route('admin.blog.generation.history') }}" class="inline-flex shrink-0 items-center gap-1 text-[13px] font-medium text-secondary transition-colors hover:text-primary">
                    {{ __('Tout l\'historique') }} <x-ui::icon name="arrow-right" class="h-3.5 w-3.5" />
                </a>
            </x-slot:actions>

            @if ($overview->latest === [])
                <x-ui::empty-state icon="sparkles" :title="__('Aucune génération pour le moment.')"
                    :description="__('La première apparaîtra ici dès son lancement.')" />
            @else
                <ul class="divide-y divide-subtle" data-latest-generations>
                    @foreach ($overview->latest as $generation)
                        @php
                            $detailUrl = route('admin.blog.generation.show', ['generation' => $generation->id]);
                        @endphp
                        {{-- Toute la ligne ouvre la generation, comme dans l'historique : le lien du sujet s'etend sur
                             la ligne entiere, et les boutons de la ligne passent au-dessus de lui. --}}
                        <li wire:key="latest-{{ $generation->id }}" class="relative flex items-center gap-3 px-5 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-white/5" data-generation="{{ $generation->id }}" data-generation-status="{{ $generation->status->value }}">
                            <div class="min-w-0 flex-1">
                                <a href="{{ $detailUrl }}" class="block truncate text-[13px] font-medium text-primary after:absolute after:inset-0">{{ $generation->subject ?? __('Sujet choisi par l\'IA') }}</a>
                                <p class="mt-0.5 truncate text-[12px] text-muted">
                                    @if ($generation->outcome)
                                        <span class="text-secondary">{{ $generation->outcome }}</span> ·
                                    @endif
                                    {{ $generation->trigger->label() }} · {{ $generation->createdAt->format('d/m/Y H:i') }} (UTC)
                                </p>
                            </div>
                            <span class="hidden shrink-0 text-[12px] tabular-nums text-muted sm:block">{{ $generation->cost }}</span>
                            <x-admin.blog.generation-status :status="$generation->status" class="shrink-0" />
                            @if ($generation->isRetryable())
                                <x-ui::button variant="secondary" size="compact" class="relative z-10 shrink-0" wire:click="retry({{ $generation->id }})" :loading="true" target="retry({{ $generation->id }})">
                                    <x-ui::icon name="arrow-path" /> {{ __('Relancer') }}
                                </x-ui::button>
                            @elseif ($generation->postId)
                                <x-ui::button variant="secondary" size="compact" class="relative z-10 shrink-0" :href="route('admin.blog.posts.edit', ['post' => $generation->postId])">{{ __('Ouvrir le brouillon') }}</x-ui::button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-admin.panel>

        {{-- Pour qui veille sur le site : les generations peuvent-elles demarrer, la cle du fournisseur est-elle en
             place. La cle elle-meme n'est jamais affichee. Le mode d'essai (etapes factices, en local seulement) se
             dit ici, en texte neutre, et nulle part ailleurs. --}}
        <p class="flex flex-wrap items-center gap-x-5 gap-y-1 text-[12px] text-muted" data-service-state>
            <span class="font-medium text-secondary">{{ __('État du service') }}</span>
            <span class="inline-flex items-center gap-1.5" data-service="worker">
                <span @class(['h-1.5 w-1.5 rounded-full', 'bg-emerald-500' => $overview->workerListening, 'bg-amber-500' => ! $overview->workerListening])></span>
                {{ $overview->workerListening ? __('Service de génération en marche') : __('Service de génération à l\'arrêt') }}
            </span>
            <span class="inline-flex items-center gap-1.5" data-service="key">
                <span @class(['h-1.5 w-1.5 rounded-full', 'bg-emerald-500' => $overview->apiKeyPresent, 'bg-amber-500' => ! $overview->apiKeyPresent])></span>
                {{ $overview->apiKeyPresent ? __('Clé du fournisseur d\'IA en place') : __('Clé du fournisseur d\'IA absente') }}
            </span>
            @if ($overview->simulated)
                <span class="inline-flex items-center gap-1.5" data-service="trial">
                    <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                    {{ __('Mode d\'essai, aucun modèle n\'est appelé') }}
                </span>
            @endif
        </p>
    @enderror
</div>
