{{-- Generation d'articles par IA. `pending` : la generation visee par la fenetre d'arret (donnee par le bouton
     « Arreter » du journal ou de la carte de suivi, qui previent par l'evenement generation-stop-asked). --}}
<div x-data="{ pending: null }"
    x-on:generation-stop-asked="pending = $event.detail.id; $dispatch('ui-open-modal', 'stop-generation')">
    <x-ui::page-header :title="__('Génération IA')" :description="__('Des brouillons d\'articles proposés par l\'IA. Rien n\'est publié sans votre validation.')" class="mb-6">
        <x-ui::button variant="secondary" :href="route('admin.blog.generation.settings')"><x-ui::icon name="cog-6-tooth" /> {{ __('Réglages') }}</x-ui::button>
    </x-ui::page-header>

    @error('generation-load-failed')
        <x-ui::card :padding="false">
            <x-ui::load-failure :title="__('Le chargement de la génération par IA a échoué.')"
                :description="__('Réessayez ; si le problème persiste, contactez-nous.')" retry="$refresh" />
        </x-ui::card>
    @else
        @if ($overview->unavailableReason)
            <x-ui::alert type="error" class="mb-6" :title="__('La génération n\'est pas disponible')">{{ $overview->unavailableReason }}</x-ui::alert>
        @elseif ($overview->simulated)
            <x-ui::alert type="info" class="mb-6" :title="__('Étapes factices')">{{ __('Aucun modèle n\'est appelé et aucun article n\'est créé : les étapes sont simulées, avec un coût fictif. Ce mode sert aux essais et n\'existe pas en production.') }}</x-ui::alert>
        @elseif (! $overview->apiKeyPresent)
            <x-ui::alert type="warning" class="mb-6" :title="__('Clé du fournisseur d\'IA absente')">{{ __('Sans cette clé, aucune génération ne peut aboutir. Elle se renseigne dans la configuration du site, pas ici.') }}</x-ui::alert>
        @endif

        @unless ($overview->workerListening)
            <x-ui::alert type="warning" class="mb-6" :title="__('Aucun traitement n\'est à l\'écoute')">{{ __('Une génération demandée maintenant attendra que les tâches automatiques du site repartent.') }}</x-ui::alert>
        @endunless

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-ui::stat-card icon="currency-dollar" :label="__('Dépense du mois')" :value="$overview->spent" data-stat="spend"
                :description="__('sur :cap de plafond', ['cap' => $overview->cap])">
                <x-ui::progress class="mt-3" :value="$overview->spentPercent" :color="$overview->capReached ? 'red' : 'indigo'" :show-value="false" />
            </x-ui::stat-card>

            <x-ui::stat-card icon="clock" :label="__('Génération automatique')" data-stat="automatic"
                :value="$overview->settings->enabled ? __('Activée') : __('Désactivée')" :description="$overview->automaticRhythm" />

            <x-ui::stat-card icon="signal" :label="__('Traitement')" data-stat="worker"
                :value="$overview->workerListening ? __('À l\'écoute') : __('À l\'arrêt')"
                :description="$overview->apiKeyPresent ? __('Clé du fournisseur d\'IA présente') : __('Clé du fournisseur d\'IA absente')" />
        </div>

        <livewire:admin.blog.generation-progress />

        <x-admin.panel class="mb-6" :title="__('Lancer une génération')"
            :description="__('Laissez les deux champs vides pour laisser l\'IA choisir le sujet.')">
            <form wire:submit="launch" class="space-y-4 p-5">
                <x-ui::form-group :label="__('Sujet (facultatif)')" for="generation-topic" :error="$errors->first('topicHint')">
                    <x-ui::input id="generation-topic" wire:model="topicHint" maxlength="300" :error="$errors->has('topicHint')"
                        :placeholder="__('Par exemple : l\'étiquetage des jouets vendus sur Etsy')" />
                </x-ui::form-group>

                <x-ui::form-group :label="__('Consignes (facultatif)')" for="generation-instructions" :error="$errors->first('instructions')"
                    :hint="__('Un angle, un lecteur visé, un point à traiter ou à laisser de côté.')">
                    <x-ui::textarea id="generation-instructions" wire:model="instructions" rows="3" maxlength="2000" :error="$errors->has('instructions')" />
                </x-ui::form-group>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[12px] leading-relaxed text-muted">
                        @if ($overview->capReached)
                            {{ __('Le plafond de dépense du mois est atteint : relevez-le dans les réglages pour lancer une génération.') }}
                        @else
                            {{ __('La génération démarre en quelques secondes et dure plusieurs minutes. Vous pouvez quitter cette page : un e-mail part quand elle est terminée.') }}
                        @endif
                    </p>
                    <x-ui::button type="submit" :loading="true" target="launch" class="shrink-0" :disabled="$overview->capReached || $overview->unavailableReason !== null">
                        <x-ui::icon name="sparkles" /> {{ __('Générer un article') }}
                    </x-ui::button>
                </div>
            </form>
        </x-admin.panel>

        <x-admin.panel :title="__('Journal')" :description="__('Une ligne par génération, les plus récentes d\'abord.')">
            @if ($generations->total() === 0)
                <x-ui::empty-state icon="sparkles" :title="__('Aucune génération pour le moment.')"
                    :description="__('La première apparaîtra ici dès son lancement.')" />
            @else
                <x-ui::table>
                    <x-ui::table.head>
                        <x-ui::table.header-cell :first="true">{{ __('Date (UTC)') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Résultat') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Sujet ou raison') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell hidden="lg">{{ __('Modèles') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell hidden="lg" align="right">{{ __('Jetons') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell hidden="sm" align="right">{{ __('Coût') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell hidden="md" align="right">{{ __('Durée') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell :last="true"><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.header-cell>
                    </x-ui::table.head>
                    <x-ui::table.body>
                        @foreach ($generations as $generation)
                            @php($detailUrl = route('admin.blog.generation.show', ['generation' => $generation->id]))
                            {{-- Toute la ligne ouvre le detail ; la date reste un vrai lien pour le clavier. --}}
                            <x-ui::table.row wire:key="generation-{{ $generation->id }}" data-generation="{{ $generation->id }}" data-generation-status="{{ $generation->status->value }}"
                                x-on:click="if (! $event.target.closest('a, button')) window.location.href = '{{ $detailUrl }}'">
                                <x-ui::table.cell :first="true" variant="primary">
                                    <a href="{{ $detailUrl }}" class="font-medium text-primary">{{ $generation->createdAt->format('d/m/Y H:i') }}</a>
                                    <span class="block text-[11px] font-normal text-muted">{{ $generation->trigger->label() }}</span>
                                </x-ui::table.cell>
                                <x-ui::table.cell><x-admin.blog.generation-status :status="$generation->status" /></x-ui::table.cell>
                                <x-ui::table.cell :nowrap="false">
                                    <span class="block max-w-xs">
                                        {{ $generation->subject ?? __('Sujet choisi par l\'IA') }}
                                        @if ($generation->outcome)
                                            <span class="block text-[12px] text-muted">{{ $generation->outcome }}</span>
                                        @endif
                                    </span>
                                </x-ui::table.cell>
                                <x-ui::table.cell hidden="lg" :nowrap="false" variant="muted">
                                    @forelse ($generation->models as $model)
                                        <span class="block">{{ $model }}</span>
                                    @empty
                                        -
                                    @endforelse
                                </x-ui::table.cell>
                                <x-ui::table.cell hidden="lg" align="right" variant="muted">
                                    @if ($generation->inputTokens + $generation->outputTokens > 0)
                                        {{ number_format($generation->inputTokens, 0, ',', ' ') }} / {{ number_format($generation->outputTokens, 0, ',', ' ') }}
                                    @else
                                        -
                                    @endif
                                </x-ui::table.cell>
                                <x-ui::table.cell hidden="sm" align="right">{{ $generation->cost }}</x-ui::table.cell>
                                <x-ui::table.cell hidden="md" align="right" variant="muted">{{ $generation->duration ?? '-' }}</x-ui::table.cell>
                                <x-ui::table.cell :last="true" align="right">
                                    @if ($generation->isRetryable())
                                        <x-ui::button variant="secondary" size="compact" wire:click="retry({{ $generation->id }})" :loading="true" target="retry({{ $generation->id }})">
                                            <x-ui::icon name="arrow-path" /> {{ __('Relancer') }}
                                        </x-ui::button>
                                    @elseif ($generation->isCancellable())
                                        <x-ui::button variant="secondary" size="compact" x-on:click="$dispatch('generation-stop-asked', { id: {{ $generation->id }} })">
                                            <x-ui::icon name="stop" /> {{ __('Arrêter') }}
                                        </x-ui::button>
                                    @endif
                                </x-ui::table.cell>
                            </x-ui::table.row>
                        @endforeach
                    </x-ui::table.body>
                </x-ui::table>

                <x-ui::pagination :paginator="$generations" mode="livewire" />
            @endif
        </x-admin.panel>
    @enderror

    <x-ui::modal name="stop-generation" variant="confirm" :title="__('Arrêter cette génération ?')">
        <p>{{ __('Une génération en attente est arrêtée tout de suite. Une génération déjà commencée finit l\'étape en cours, puis s\'arrête : ce qui a été dépensé jusque-là reste dépensé.') }}</p>

        <x-slot:actions>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'stop-generation')">{{ __('Continuer la génération') }}</x-ui::button>
            <x-ui::button variant="danger" x-on:click="$wire.cancel(pending); $dispatch('ui-close-modal', 'stop-generation')">{{ __('Arrêter') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>
</div>
