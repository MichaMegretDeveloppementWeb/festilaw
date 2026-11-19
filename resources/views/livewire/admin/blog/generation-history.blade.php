{{-- Historique des generations. `pending` : la generation visee par la fenetre d'arret (donnee par le bouton
     « Arrêter » de sa ligne). Chaque ligne mene a la page de sa generation, ou se lisent ses etapes, ses
     modeles et ses tokens. --}}
<div x-data="{ pending: null }">
    @include('livewire.admin.blog.generation.header', ['page' => 'history'])

    @error('history-load-failed')
        <x-ui::load-failure :title="__('Le chargement de l\'historique a échoué.')"
            :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
    @else
        {{-- Le tableau sur la page blanche, sans carte. Ses filets et son texte partent du bord du titre de la page
             (kit.css retire la marge des cellules extremes). --}}
        <div class="relative border-t border-default">
            {{-- Voile pendant un changement de page, une relance ou un arret. --}}
            <div wire:loading.delay.long wire:target="gotoPage, nextPage, previousPage, retry, cancel" class="absolute inset-0 z-10 bg-surface/70">
                <div class="flex h-full items-center justify-center"><x-ui::spinner size="lg" /></div>
            </div>

            @if ($generations->total() === 0)
                <x-ui::empty-state icon="sparkles" :title="__('Aucune génération pour le moment.')"
                    :description="__('La première apparaîtra ici dès son lancement.')" />
            @else
                <x-ui::table>
                    <x-ui::table.head>
                        <x-ui::table.header-cell :first="true">{{ __('Sujet') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Résultat') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell hidden="sm" align="right">{{ __('Coût') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell hidden="md" align="right">{{ __('Durée') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell :last="true"><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.header-cell>
                    </x-ui::table.head>
                    <x-ui::table.body>
                        @foreach ($generations as $generation)
                            @php
                                $detailUrl = route('admin.blog.generation.show', ['generation' => $generation->id]);
                            @endphp
                            {{-- Toute la ligne ouvre la generation ; le sujet reste un vrai lien pour le clavier. --}}
                            <x-ui::table.row wire:key="generation-{{ $generation->id }}" data-generation="{{ $generation->id }}" data-generation-status="{{ $generation->status->value }}"
                                x-on:click="if (! $event.target.closest('a, button')) window.location.href = '{{ $detailUrl }}'">
                                <x-ui::table.cell :first="true" :nowrap="false" variant="primary">
                                    <a href="{{ $detailUrl }}" class="block min-w-[14rem] max-w-xl font-medium text-primary">{{ $generation->subject ?? __('Sujet choisi par l\'IA') }}</a>
                                    <span class="mt-0.5 block text-[11px] font-normal text-muted">{{ $generation->trigger->label() }} · {{ $generation->createdAt->format('d/m/Y H:i') }} (UTC)</span>
                                </x-ui::table.cell>
                                <x-ui::table.cell :nowrap="false">
                                    <x-admin.blog.generation-status :status="$generation->status" />
                                    @if ($generation->outcome)
                                        <span class="mt-1 block min-w-[12rem] max-w-xs text-[12px] text-muted">{{ $generation->outcome }}</span>
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
                                        <x-ui::button variant="secondary" size="compact" data-stop-generation
                                            x-on:click="pending = {{ $generation->id }}; $dispatch('ui-open-modal', 'stop-generation')">
                                            <x-ui::icon name="stop" /> {{ __('Arrêter') }}
                                        </x-ui::button>
                                    @elseif ($generation->postId)
                                        <x-ui::button variant="secondary" size="compact" :href="route('admin.blog.posts.edit', ['post' => $generation->postId])">{{ __('Ouvrir le brouillon') }}</x-ui::button>
                                    @endif
                                </x-ui::table.cell>
                            </x-ui::table.row>
                        @endforeach
                    </x-ui::table.body>
                </x-ui::table>

                <x-ui::pagination :paginator="$generations" mode="livewire" />
            @endif
        </div>

        <x-ui::modal name="stop-generation" variant="confirm" :title="__('Arrêter cette génération ?')">
            <p>{{ __('La génération s\'arrête tout de suite. Si une étape est en cours, son appel à l\'IA est déjà parti et sera facturé, mais son résultat ne sera pas gardé. Ce qui a déjà été dépensé reste dépensé.') }}</p>

            <x-slot:actions>
                <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'stop-generation')">{{ __('Continuer la génération') }}</x-ui::button>
                <x-ui::button variant="danger" x-on:click="$wire.cancel(pending); $dispatch('ui-close-modal', 'stop-generation')">{{ __('Arrêter') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::modal>
    @enderror
</div>
