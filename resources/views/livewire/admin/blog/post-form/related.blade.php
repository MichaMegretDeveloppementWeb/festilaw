{{-- Articles lies de l'article, dans leur ordre (glisser pour le changer). Le bouton ouvre une fenetre
     (post-form/dialogs) qui liste les articles pas encore choisis, toutes categories et tous statuts
     confondus ; elle n'est lue par le serveur que pendant qu'elle est ouverte. Le changement est pris en
     compte a l'enregistrement.
     $related : articles choisis ; $relatedLimit. --}}
<div>
    <div class="mb-1.5 flex items-baseline justify-between gap-3">
        <p class="text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Articles liés') }}</p>
        <span class="text-[11px] tabular-nums text-muted">{{ count($related) }} / {{ $relatedLimit }}</span>
    </div>

    @if ($related === [])
        <p class="text-[13px] text-secondary">{{ __('Si aucun article n\'est choisi, les trois derniers articles de la catégorie sont proposés.') }}</p>
    @else
        <ol wire:sort="reorderRelated" class="space-y-1.5" data-related>
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

    @if (count($related) < $relatedLimit)
        <x-ui::button variant="secondary" size="compact" class="mt-2" data-add-related x-on:click="openRelated()">
            <x-ui::icon name="plus" /> {{ __('Ajouter un article') }}
        </x-ui::button>
    @else
        <p class="mt-2 text-[12px] text-muted">{{ __('Un article garde :count articles liés au plus.', ['count' => $relatedLimit]) }}</p>
    @endif

    <p class="mt-1.5 text-[12px] leading-relaxed text-muted">{{ __('Affichés en bas de l\'article, dans « À lire aussi ». Seuls les articles publiés y apparaissent.') }}</p>
</div>
