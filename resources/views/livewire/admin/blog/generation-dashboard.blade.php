{{-- Page d'arrivee de la generation par IA, sur page blanche : une bande de quatre chiffres (depense,
     automatique, brouillons a relire, en cours), les dernieres generations, puis l'etat du service. Le
     lancement est une fenetre de l'en-tete ; l'historique s'ouvre par son lien, les reglages par la roue crantee.
     Rien n'apparait ni ne pousse la page : la cellule « En cours » garde sa taille, qu'une generation tourne ou non. --}}
<div>
    @include('livewire.admin.blog.generation.header', ['page' => 'index'])

    @error('generation-load-failed')
        <x-ui::load-failure :title="__('Le chargement de la génération par IA a échoué.')"
            :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
    @else
        {{-- Une alerte quand quelque chose empeche de generer, avec ce que la personne peut faire, et l'avertissement
             du plafond du mois, qui n'empeche rien. --}}
        @if ($overview->unavailableReason)
            <x-ui::alert type="error" class="mb-6" :title="__('La génération n\'est pas disponible')">{{ $overview->unavailableReason }} {{ __('Contactez l\'assistance technique.') }}</x-ui::alert>
        @elseif (! $overview->simulated && ! $overview->apiKeyPresent)
            <x-ui::alert type="warning" class="mb-6" :title="__('La génération n\'est pas disponible pour le moment')">{{ __('Contactez l\'assistance technique.') }}</x-ui::alert>
        @endif

        @unless ($overview->workerListening)
            {{-- Dit avec les mots de la personne qui ecrit des articles : ce qu'elle constate, ce que cela change pour elle, quoi faire. --}}
            <x-ui::alert type="warning" class="mb-6" :title="__('Les générations ne démarrent pas pour le moment')">{{ __('Un article demandé maintenant restera en attente. Si cela dure, contactez l\'assistance technique.') }}</x-ui::alert>
        @endunless

        @if ($overview->monthlyWarning)
            @include('livewire.admin.blog.generation.monthly-warning', ['warning' => $overview->monthlyWarning, 'class' => 'mb-6'])
        @endif

        <x-admin.stats :columns="4">
            <x-admin.stats.item icon="currency-dollar" :label="__('Dépense du mois')" data-stat="spend">
                {{ $overview->spent }}
                <x-slot:extra>
                    {{ __('sur :cap de plafond', ['cap' => $overview->cap]) }}
                    <x-ui::progress class="mt-2.5" :value="$overview->spentPercent" :color="$overview->spendBarColor()" :show-value="false" />
                </x-slot:extra>
            </x-admin.stats.item>

            <x-admin.stats.item icon="clock" :label="__('Génération automatique')" data-stat="automatic">
                {{ $overview->settings->enabled ? __('Activée') : __('Désactivée') }}
                <x-slot:extra>{{ $overview->automaticRhythm }}</x-slot:extra>
            </x-admin.stats.item>

            <x-admin.stats.item icon="document-text" :label="__('Brouillons à relire')" data-stat="drafts">
                {{ $overview->pendingDrafts }}
                <x-slot:extra>
                    {{ trans_choice('{0} Aucun brouillon généré n\'attend de relecture|{1} brouillon généré attend votre relecture|[2,*] brouillons générés attendent votre relecture', $overview->pendingDrafts) }}
                    @if ($overview->pendingDrafts > 0)
                        <a href="{{ route('admin.blog.posts.index', ['origin' => 'ai', 'status' => 'draft']) }}"
                            class="mt-2.5 flex items-center gap-1 font-medium text-secondary transition-colors hover:text-primary">{{ __('Voir les brouillons') }} <x-ui::icon name="arrow-right" class="h-3.5 w-3.5" /></a>
                    @endif
                </x-slot:extra>
            </x-admin.stats.item>

            <livewire:admin.blog.generation-activity />
        </x-admin.stats>

        <x-admin.section class="mt-12" :title="__('Dernières générations')">
            <x-slot:actions>
                <a href="{{ route('admin.blog.generation.history') }}" class="inline-flex items-center gap-1 font-medium text-secondary transition-colors hover:text-primary">
                    {{ __('Tout l\'historique') }} <x-ui::icon name="arrow-right" class="h-3.5 w-3.5" />
                </a>
            </x-slot:actions>

            @if ($overview->latest === [])
                <x-ui::empty-state icon="sparkles" :title="__('Aucune génération pour le moment.')"
                    :description="__('La première apparaîtra ici dès son lancement.')" />
            @else
                {{-- Toute la ligne ouvre la generation, comme dans l'historique : le lien du sujet s'etend sur la ligne
                     entiere, et les boutons de la ligne passent au-dessus de lui. Les lignes debordent de la colonne de
                     la meme marge que leurs filets, pour que le texte reste aligne sur les titres. --}}
                <ul class="-mx-4 divide-y divide-[var(--ui-border)] border-b border-default sm:-mx-5" data-latest-generations>
                    @foreach ($overview->latest as $generation)
                        @php
                            $detailUrl = route('admin.blog.generation.show', ['generation' => $generation->id]);
                        @endphp
                        <li wire:key="latest-{{ $generation->id }}" class="relative flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-elevated sm:px-5" data-generation="{{ $generation->id }}" data-generation-status="{{ $generation->status->value }}">
                            <div class="min-w-0 flex-1">
                                <a href="{{ $detailUrl }}" class="block truncate text-[14px] font-medium text-primary after:absolute after:inset-0">{{ $generation->subject ?? __('Sujet choisi par l\'IA') }}</a>
                                <p class="mt-0.5 truncate text-[12px] text-muted">
                                    @if ($generation->outcome)
                                        <span class="text-secondary">{{ $generation->outcome }}</span>,
                                    @endif
                                    {{ $generation->trigger->label() }}, {{ $generation->createdAt->format('d/m/Y H:i') }} (UTC)
                                </p>
                            </div>
                            <span class="hidden shrink-0 text-[13px] tabular-nums text-secondary sm:block">{{ $generation->cost }}</span>
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
        </x-admin.section>

        {{-- Pour qui veille sur le site : les generations peuvent-elles demarrer, la cle du fournisseur est-elle en
             place. La cle elle-meme n'est jamais affichee. Le mode d'essai (etapes factices, en local seulement) se
             dit ici, en texte neutre, et nulle part ailleurs. --}}
        <p class="mt-10 flex flex-wrap items-center gap-x-5 gap-y-1 text-[12px] text-muted" data-service-state>
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
