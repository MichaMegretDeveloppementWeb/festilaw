{{-- Sources de l'article : intitule et lien, dans l'ordre d'affichage (glisser pour ordonner).
     Une source s'ajoute et se modifie dans une fenetre (post-form/dialogs) : elle n'entre dans la liste
     qu'une fois valide, jamais sous la forme d'une ligne vide. --}}
<x-admin.section class="mt-12" :title="__('Sources')" :description="__('Affichées à la fin de l\'article, dans les trois langues.')">
    <x-slot:actions>
        @if (count($sources) < $sourceLimit)
            <x-ui::button variant="secondary" size="compact" class="shrink-0" data-add-source x-on:click="openSource()">
                <x-ui::icon name="plus" /> {{ __('Ajouter') }}
            </x-ui::button>
        @endif
    </x-slot:actions>

    @error('sources')
        <p class="pt-4 text-[12px] text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror

    @if ($sources === [])
        <p class="py-6 text-[13px] text-secondary">{{ __('Aucune source. Un article qui cite ses sources inspire davantage confiance.') }}</p>
    @else
        <ul wire:sort="sortSources" class="-mx-4 divide-y divide-[var(--ui-border)] border-b border-default sm:-mx-5" data-sources>
            @foreach ($sources as $index => $source)
                @php
                    $sourceError = $errors->first('sources.'.$index.'.label') ?: $errors->first('sources.'.$index.'.url');
                @endphp
                <li wire:key="source-{{ $index }}-{{ md5($source['label'].'|'.$source['url']) }}" wire:sort:item="{{ $index }}" class="flex items-center gap-2 px-3 py-3 sm:gap-3 sm:px-4">
                    <span wire:sort:handle data-handle x-on:mouseup="dirty = true"
                        class="flex shrink-0 cursor-grab items-center self-center rounded-lg p-1.5 text-muted transition hover:text-secondary" title="{{ __('Glisser pour changer l\'ordre') }}">
                        <x-ui::icon name="bars-2" />
                    </span>
                    <div wire:sort:ignore class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-medium text-primary">{{ $source['label'] }}</p>
                        <p class="truncate text-[12px] text-muted">{{ $source['url'] }}</p>
                        @if ($sourceError)
                            <p class="mt-0.5 text-[12px] text-red-700 dark:text-red-400">{{ $sourceError }}</p>
                        @endif
                    </div>
                    <button type="button" wire:sort:ignore x-on:click="openSource({{ $index }}, @js($source['label']), @js($source['url']))"
                        class="flex min-h-11 min-w-11 shrink-0 items-center justify-center self-center rounded-lg text-muted transition hover:text-primary sm:min-h-0 sm:min-w-0 sm:p-1.5"
                        aria-label="{{ __('Modifier la source :number', ['number' => $index + 1]) }}" title="{{ __('Modifier cette source') }}">
                        <x-ui::icon name="pencil-square" />
                    </button>
                    <button type="button" wire:sort:ignore wire:click="removeSource({{ $index }})" x-on:click="dirty = true"
                        class="flex min-h-11 min-w-11 shrink-0 items-center justify-center self-center rounded-lg text-muted transition hover:text-red-700 sm:min-h-0 sm:min-w-0 sm:p-1.5 dark:hover:text-red-400"
                        aria-label="{{ __('Retirer la source :number', ['number' => $index + 1]) }}" title="{{ __('Retirer cette source') }}">
                        <x-ui::icon name="trash" />
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</x-admin.section>
