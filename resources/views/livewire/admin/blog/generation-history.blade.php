{{-- Historique des generations, en liste a filets, avec la meme ligne que le tableau de bord (generation/journal-row,
     decision de Micha du 19/11 : le tableau etait moins lisible et debordait sur telephone). Chaque ligne mene a
     la page de sa generation, ou se lisent ses etapes, ses modeles et ses tokens. `pending` : la generation
     visee par la fenetre d'arret (donnee par le bouton « Arrêter » de sa ligne). --}}
<div x-data="{ pending: null }">
    @include('livewire.admin.blog.generation.header', ['page' => 'history'])

    @error('history-load-failed')
        <x-ui::load-failure :title="__('Le chargement de l\'historique a échoué.')"
            :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
    @else
        <div class="relative border-t border-default">
            {{-- Voile pendant un changement de page, une relance ou un arret. --}}
            <div wire:loading.delay.long wire:target="gotoPage, nextPage, previousPage, retry, cancel" class="absolute inset-0 z-20 bg-surface/70">
                <div class="flex h-full items-center justify-center"><x-ui::spinner size="lg" /></div>
            </div>

            @if ($generations->total() === 0)
                <x-ui::empty-state icon="sparkles" :title="__('Aucune génération pour le moment.')"
                    :description="__('La première apparaîtra ici dès son lancement.')" />
            @else
                <ul class="divide-y divide-[var(--ui-border)]" data-generation-history>
                    @foreach ($generations as $generation)
                        @include('livewire.admin.blog.generation.journal-row', ['generation' => $generation, 'stoppable' => true])
                    @endforeach
                </ul>

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
