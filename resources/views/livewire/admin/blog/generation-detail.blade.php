{{-- Detail d'une generation : son issue, puis ses etapes. L'ecran se relit toutes les 2 secondes tant que la
     generation n'est pas terminee. --}}
<div class="mx-auto max-w-4xl" data-generation-detail="{{ $generation?->status->value ?? 'missing' }}">
    @if ($generation?->isActive())
        <div wire:poll.2s wire:key="generation-detail-poll" class="hidden"></div>
    @endif

    <a href="{{ route('admin.blog.generation.index') }}" class="inline-flex items-center gap-1.5 text-[13px] text-muted transition-colors hover:text-primary">
        <x-ui::icon name="arrow-left" class="h-3.5 w-3.5" /> {{ __('Génération IA') }}
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
            <x-ui::page-header :title="__('Génération n° :id', ['id' => $generation->id])"
                :description="$generation->subject ?? __('Sujet choisi par l\'IA')" class="mb-6 mt-2">
                <x-admin.blog.generation-status :status="$generation->status" />

                @if ($generation->isRetryable())
                    <x-ui::button wire:click="retry" :loading="true" target="retry"><x-ui::icon name="arrow-path" /> {{ __('Relancer') }}</x-ui::button>
                @elseif ($generation->isActive() && ! $generation->cancelRequested)
                    <x-ui::button variant="secondary" x-on:click="$dispatch('ui-open-modal', 'stop-generation')"><x-ui::icon name="stop" /> {{ __('Arrêter') }}</x-ui::button>
                @endif
            </x-ui::page-header>

            @if ($generation->cancelRequested && $generation->isActive())
                <x-ui::alert type="warning" class="mb-6" :title="__('Arrêt demandé')">{{ __('La génération s\'arrêtera après l\'étape en cours.') }}</x-ui::alert>
            @endif

            {{-- Ce qui s'est passe et quoi faire, en clair. La cause technique d'un echec ne s'affiche pas : elle est au journal du serveur. --}}
            @if ($generation->outcome)
                <x-ui::alert :type="$generation->isRetryable() ? 'error' : 'info'" class="mb-6" :title="$generation->outcome">{{ $generation->advice }}</x-ui::alert>
            @endif

            <x-admin.panel class="mb-6" :title="__('Résumé')">
                <x-ui::description-list variant="inline">
                    <x-ui::description-list.item variant="inline" :label="__('Déclenchement')">{{ $generation->trigger->label() }}</x-ui::description-list.item>
                    <x-ui::description-list.item variant="inline" :label="__('Demandée le (UTC)')">{{ $generation->createdAt->format('d/m/Y H:i:s') }}</x-ui::description-list.item>
                    @if ($generation->startedAt)
                        <x-ui::description-list.item variant="inline" :label="__('Prise en charge le (UTC)')">{{ $generation->startedAt->format('d/m/Y H:i:s') }}</x-ui::description-list.item>
                    @endif
                    @if ($generation->finishedAt)
                        <x-ui::description-list.item variant="inline" :label="__('Terminée le (UTC)')">{{ $generation->finishedAt->format('d/m/Y H:i:s') }}</x-ui::description-list.item>
                    @endif
                    @if ($generation->duration)
                        <x-ui::description-list.item variant="inline" :label="$generation->isActive() ? __('En cours depuis') : __('Durée')">{{ $generation->duration }}</x-ui::description-list.item>
                    @endif
                    <x-ui::description-list.item variant="inline" :label="__('Coût')" data-generation-cost>{{ $generation->cost }}</x-ui::description-list.item>
                    @if ($generation->topicHint)
                        <x-ui::description-list.item variant="inline" :label="__('Sujet imposé')">{{ $generation->topicHint }}</x-ui::description-list.item>
                    @endif
                    @if ($generation->instructions)
                        <x-ui::description-list.item variant="inline" :label="__('Consignes')"><span class="whitespace-pre-line">{{ $generation->instructions }}</span></x-ui::description-list.item>
                    @endif
                    @if ($generation->postId)
                        <x-ui::description-list.item variant="inline" :label="__('Brouillon')">
                            <a href="{{ route('admin.blog.posts.edit', ['post' => $generation->postId]) }}" class="underline underline-offset-2">{{ __('Ouvrir le brouillon') }}</a>
                        </x-ui::description-list.item>
                    @endif
                </x-ui::description-list>
            </x-admin.panel>

            <x-admin.panel :title="__('Étapes')"
                :description="trans_choice('{0} Aucune étape terminée|{1} :count étape terminée sur :total|[2,*] :count étapes terminées sur :total', $generation->completedSteps(), ['count' => $generation->completedSteps(), 'total' => count($generation->steps)])">
                @include('livewire.admin.blog.generation.steps', ['steps' => $generation->steps, 'detailed' => true])
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
