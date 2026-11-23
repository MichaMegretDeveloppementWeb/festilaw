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
        @elseif (! $overview->simulated && ! $overview->key->usable)
            {{-- La cliente peut y remedier elle-meme depuis le 23/11 : la cle se colle dans les reglages. --}}
            <x-ui::alert type="warning" class="mb-6" :title="__('La génération n\'est pas disponible pour le moment')" data-key-alert>
                {{ $overview->key->sentence }}
                <a href="{{ route('admin.blog.generation.settings') }}#cle-openrouter" class="font-medium underline underline-offset-2">{{ __('Ouvrir les réglages') }}</a>
            </x-ui::alert>
        @endif

        @unless ($overview->workerListening)
            {{-- Dit avec les mots de la personne qui ecrit des articles : ce qu'elle constate, ce que cela change pour elle, quoi faire. --}}
            <x-ui::alert type="warning" class="mb-6" :title="__('Les générations ne démarrent pas pour le moment')">{{ __('Un article demandé maintenant restera en attente. Si cela persiste, contactez l\'assistance technique.') }}</x-ui::alert>
        @endunless

        @if ($overview->rulesReferenceDue)
            <x-ui::alert type="warning" class="mb-6" :title="__('La fiche des règles européennes est à relire')" data-rules-reference-due>{{ __('La génération s\'appuie sur une fiche des règles européennes qui a dépassé sa date de relecture. Le droit a pu changer depuis. Contactez l\'assistance technique pour la relire ensemble.') }}</x-ui::alert>
        @endif

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

            {{-- La date du prochain lot vient du calendrier du planificateur ; le lien mene droit a la section de la
                 generation automatique, en bas des reglages (decisions de Micha du 23/11). --}}
            <x-admin.stats.item icon="clock" :label="__('Génération automatique')" data-stat="automatic">
                @if ($overview->automaticPaused())
                    {{ __('En pause') }}
                @else
                    {{ $overview->settings->enabled ? __('Activée') : __('Désactivée') }}
                @endif
                <x-slot:extra>
                    <span class="block">{{ $overview->automaticPaused() ? __('Reprend quand les brouillons sont relus') : $overview->automaticRhythm }}</span>
                    @if ($overview->nextLaunch)
                        <span class="block" data-next-launch>{{ $overview->nextLaunch }}</span>
                    @endif
                    <a href="{{ route('admin.blog.generation.settings') }}#generation-automatique" data-edit-automatic
                        class="mt-1 flex items-center gap-1 font-medium text-secondary transition-colors hover:text-primary">{{ __('Modifier') }} <x-ui::icon name="arrow-right" class="h-3.5 w-3.5" /></a>
                </x-slot:extra>
            </x-admin.stats.item>

            <x-admin.stats.item icon="document-text" :label="__('Brouillons à relire')" data-stat="drafts">
                {{ $overview->pendingDrafts }}
                {{-- Le titre dit deja ce qu'est le nombre : rien d'autre que le lien vers les brouillons (Micha, 19/11). --}}
                @if ($overview->pendingDrafts > 0)
                    <x-slot:extra>
                        <a href="{{ route('admin.blog.posts.index', ['origin' => 'ai', 'status' => 'draft']) }}"
                            class="flex items-center gap-1 font-medium text-secondary transition-colors hover:text-primary">{{ __('Voir les brouillons') }} <x-ui::icon name="arrow-right" class="h-3.5 w-3.5" /></a>
                    </x-slot:extra>
                @endif
            </x-admin.stats.item>

            <livewire:admin.blog.generation-activity />
        </x-admin.stats>

        <x-admin.section :title="__('Dernières générations')">
            <x-slot:actions>
                <a href="{{ route('admin.blog.generation.history') }}" class="inline-flex items-center gap-1 font-medium text-secondary transition-colors hover:text-primary">
                    {{ __('Tout l\'historique') }} <x-ui::icon name="arrow-right" class="h-3.5 w-3.5" />
                </a>
            </x-slot:actions>

            @if ($overview->latest === [])
                <x-ui::empty-state icon="sparkles" :title="__('Aucune génération pour le moment.')"
                    :description="__('La première apparaîtra ici dès son lancement.')" />
            @else
                {{-- La ligne est la meme que dans l'historique (generation/journal-row). --}}
                <ul class="divide-y divide-[var(--ui-border)] border-b border-default" data-latest-generations>
                    @foreach ($overview->latest as $generation)
                        @include('livewire.admin.blog.generation.journal-row', ['generation' => $generation, 'stoppable' => false])
                    @endforeach
                </ul>
            @endif
        </x-admin.section>

        {{-- Pour qui veille sur le site : les generations peuvent-elles demarrer, la cle OpenRouter des reglages est-elle
             en place, refusee ou sur le point d'expirer, la verification quotidienne des services a-t-elle trouve un
             probleme (le detail technique ne va qu'au prestataire, par le rapport d'erreurs du matin). La cle elle-meme
             n'est jamais affichee. Le mode d'essai (etapes factices, en local seulement) se dit ici, en texte neutre,
             et nulle part ailleurs. --}}
        <p class="mt-10 flex flex-wrap items-center gap-x-5 gap-y-1 text-[12px] text-muted" data-service-state>
            <span class="font-medium text-secondary">{{ __('État du service') }}</span>
            <span class="inline-flex items-center gap-1.5" data-service="worker">
                <span @class(['h-1.5 w-1.5 rounded-full', 'bg-emerald-500' => $overview->workerListening, 'bg-amber-500' => ! $overview->workerListening])></span>
                {{ $overview->workerListening ? __('Service de génération en marche') : __('Service de génération à l\'arrêt') }}
            </span>
            <span class="inline-flex items-center gap-1.5" data-service="key">
                <span @class(['h-1.5 w-1.5 rounded-full', 'bg-emerald-500' => ! $overview->key->needsAttention, 'bg-amber-500' => $overview->key->needsAttention])></span>
                {{ $overview->key->label }}
            </span>
            @if ($overview->health)
                <span class="inline-flex items-center gap-1.5" data-service="checks">
                    <span @class(['h-1.5 w-1.5 rounded-full', 'bg-emerald-500' => $overview->health->isHealthy(), 'bg-amber-500' => ! $overview->health->isHealthy()])></span>
                    {{ $overview->health->isHealthy()
                        ? __('Services vérifiés le :date', ['date' => $overview->health->checkedAt->format('d/m')])
                        : __('Problème technique signalé à l\'assistance technique') }}
                </span>
            @endif
            @if ($overview->simulated)
                <span class="inline-flex items-center gap-1.5" data-service="trial">
                    <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                    {{ __('Mode d\'essai, aucun modèle n\'est appelé') }}
                </span>
            @endif
        </p>
    @enderror
</div>
