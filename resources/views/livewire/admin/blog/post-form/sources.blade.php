{{-- Sources de l'article : intitule et lien, dans l'ordre d'affichage (glisser pour ordonner).
     Une source = une ligne : la poignee et la corbeille restent au milieu de la ligne. --}}
<x-admin.panel :title="__('Sources')" :description="__('Affichées à la fin de l\'article, dans les trois langues.')" class="overflow-hidden">
    <x-slot:actions>
        <x-ui::button variant="secondary" size="compact" class="shrink-0" wire:click="addSource" x-on:click="dirty = true">
            <x-ui::icon name="plus" /> {{ __('Ajouter') }}
        </x-ui::button>
    </x-slot:actions>

    @error('sources')
        <p class="px-5 pt-4 text-[12px] text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror

    @if ($sources === [])
        <p class="px-5 py-6 text-[13px] text-secondary">{{ __('Aucune source. Un article qui cite ses sources inspire davantage confiance.') }}</p>
    @else
        <ul wire:sort="sortSources" class="divide-y divide-subtle">
            @foreach ($sources as $index => $source)
                <li wire:key="source-{{ $index }}-{{ count($sources) }}" wire:sort:item="{{ $index }}" class="flex items-center gap-2 px-3 py-3 sm:gap-3 sm:px-5">
                    <span wire:sort:handle data-handle x-on:mouseup="dirty = true"
                        class="flex shrink-0 cursor-grab items-center self-center rounded-lg p-1.5 text-muted transition hover:text-secondary" title="{{ __('Glisser pour changer l\'ordre') }}">
                        <x-ui::icon name="bars-2" />
                    </span>
                    <div wire:sort:ignore class="grid min-w-0 flex-1 gap-2 sm:grid-cols-2 sm:gap-3">
                        <x-ui::form-group :error="$errors->first('sources.'.$index.'.label')">
                            <x-ui::input id="source-label-{{ $index }}" wire:model="sources.{{ $index }}.label" :error="$errors->has('sources.'.$index.'.label')"
                                aria-label="{{ __('Intitulé de la source :number', ['number' => $index + 1]) }}" placeholder="{{ __('Intitulé, ex. Règlement (UE) 2023/988') }}" />
                        </x-ui::form-group>
                        <x-ui::form-group :error="$errors->first('sources.'.$index.'.url')">
                            <x-ui::input type="url" id="source-url-{{ $index }}" wire:model="sources.{{ $index }}.url" :error="$errors->has('sources.'.$index.'.url')"
                                aria-label="{{ __('Lien de la source :number', ['number' => $index + 1]) }}" placeholder="https://" />
                        </x-ui::form-group>
                    </div>
                    <button type="button" wire:sort:ignore wire:click="removeSource({{ $index }})" x-on:click="dirty = true"
                        class="flex min-h-11 min-w-11 shrink-0 items-center justify-center self-center rounded-lg text-muted transition hover:text-red-700 sm:min-h-0 sm:min-w-0 sm:p-1.5 dark:hover:text-red-400"
                        aria-label="{{ __('Retirer la source :number', ['number' => $index + 1]) }}" title="{{ __('Retirer cette source') }}">
                        <x-ui::icon name="trash" />
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</x-admin.panel>
