{{-- Articles lies de l'article, dans leur ordre (glisser pour le changer). Un bouton ouvre la liste des
     articles pas encore choisis, toutes categories et tous statuts confondus ; elle n'est lue par le
     serveur que pendant qu'elle est ouverte. Le changement est pris en compte a l'enregistrement.
     $related : articles choisis ; $relatedCandidates : identifiant => libelle ; $relatedLimit. --}}
<div>
    <div class="mb-1.5 flex items-baseline justify-between gap-3">
        <p class="text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Articles liés') }}</p>
        <span class="text-[11px] tabular-nums text-muted">{{ count($related) }} / {{ $relatedLimit }}</span>
    </div>

    @if ($related === [])
        <p class="text-[13px] text-secondary">{{ __('Aucun article choisi : le site propose les trois derniers de la même catégorie.') }}</p>
    @else
        <ol wire:sort="reorderRelated" class="space-y-1.5">
            @foreach ($related as $item)
                <li wire:key="related-{{ $item['id'] }}" wire:sort:item="{{ $item['id'] }}" class="flex items-center gap-1.5 rounded-lg bg-elevated py-1 pl-1 pr-1 text-[13px]">
                    <span wire:sort:handle data-handle x-on:mouseup="dirty = true"
                        class="flex shrink-0 cursor-grab items-center rounded-lg p-1.5 text-muted transition hover:text-secondary" title="{{ __('Glisser pour changer l\'ordre') }}">
                        <x-ui::icon name="bars-2" />
                    </span>
                    <span @class(['min-w-0 flex-1 truncate', 'text-primary' => $item['title'], 'italic text-muted' => ! $item['title']]) title="{{ $item['title'] }}">{{ $item['title'] ?? __('Sans titre') }}</span>
                    @if ($item['status'] !== \App\Enums\Blog\BlogPostStatus::Published)
                        <x-admin.blog.status-badge :status="$item['status']" class="shrink-0" />
                    @endif
                    <button type="button" wire:sort:ignore wire:click="removeRelated({{ $item['id'] }})" x-on:click="dirty = true"
                        class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-muted transition hover:text-red-700 sm:min-h-0 sm:min-w-0 sm:p-1.5 dark:hover:text-red-400"
                        aria-label="{{ __('Retirer :title des articles liés', ['title' => $item['title'] ?? __('cet article')]) }}" title="{{ __('Retirer') }}">
                        <x-ui::icon name="x-mark" />
                    </button>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($pickingRelated)
        <div class="mt-2">
            @if ($relatedCandidates === [])
                <p class="text-[13px] text-secondary">{{ __('Aucun autre article à proposer.') }}</p>
            @else
                <x-ui::combobox id="related-choice" :options="$relatedCandidates" data-no-dirty
                    :placeholder="__('Choisir un article')" :search-placeholder="__('Rechercher un titre')" :empty="__('Aucun article ne correspond')"
                    x-on:change="if ($event.target.value) { dirty = true; $wire.addRelated($event.target.value) }" />
                @if (count($relatedCandidates) >= $relatedCandidateLimit)
                    <p class="mt-1.5 text-[12px] text-muted">{{ __('Seuls les :count articles les plus récents sont proposés.', ['count' => $relatedCandidateLimit]) }}</p>
                @endif
            @endif
            <button type="button" wire:click="closeRelatedPicker" class="mt-1.5 text-[12px] font-medium text-secondary underline underline-offset-2 hover:text-primary">{{ __('Annuler') }}</button>
        </div>
    @elseif (count($related) < $relatedLimit)
        <x-ui::button variant="secondary" size="compact" class="mt-2" wire:click="openRelatedPicker" :loading="true" target="openRelatedPicker">
            <x-ui::icon name="plus" /> {{ __('Ajouter un article') }}
        </x-ui::button>
    @else
        <p class="mt-2 text-[12px] text-muted">{{ __('Un article garde :count articles liés au plus.', ['count' => $relatedLimit]) }}</p>
    @endif

    <p class="mt-1.5 text-[12px] leading-relaxed text-muted">{{ __('Proposés en bas de l\'article. Un article qui n\'est pas publié n\'y apparaît pas.') }}</p>
</div>
