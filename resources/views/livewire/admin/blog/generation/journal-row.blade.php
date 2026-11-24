{{-- Une generation dans une liste, la meme au tableau de bord et dans l'historique (decision de Micha du 19/11 : le
     tableau de l'historique etait moins lisible et debordait sur telephone). Toute la ligne ouvre la page de la
     generation : le lien du sujet s'etend sur elle, et les actions passent au-dessus. Le sujet, puis le resultat,
     le lancement et la date ; a droite la duree, le cout, l'etat et l'action. L'action est un petit bouton a
     icone avec son info-bulle : les gros boutons prenaient la place, et la page de la generation garde les siens.
     $generation : BlogGenerationJournalRow. $stoppable : la liste propose d'arreter une generation en cours (la
     portee porte `pending` et la fenetre « stop-generation »).
     Une icone dit d'un coup d'oeil la sorte de generation, article, traduction ou image demandee depuis un
     article (Micha, 24/11/2026). --}}
@php
    $typeIcon = match ($generation->type) {
        App\Enums\Blog\Generation\BlogGenerationType::Article => 'document-text',
        App\Enums\Blog\Generation\BlogGenerationType::Translation => 'language',
        App\Enums\Blog\Generation\BlogGenerationType::Image => 'photo',
    };
    $detailUrl = route('admin.blog.generation.show', ['generation' => $generation->id]);
    $iconButton = 'flex min-h-11 min-w-11 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface hover:text-primary disabled:opacity-50 sm:min-h-8 sm:min-w-8';
@endphp
<li wire:key="generation-{{ $generation->id }}" class="relative flex items-center gap-3 py-3.5 transition-colors hover:bg-elevated"
    data-generation="{{ $generation->id }}" data-generation-status="{{ $generation->status->value }}" data-generation-type="{{ $generation->type->value }}">
    <span class="shrink-0 text-muted">
        <x-ui::icon :name="$typeIcon" class="h-5 w-5" />
        <span class="sr-only">{{ $generation->type->label() }}</span>
    </span>
    <div class="min-w-0 flex-1">
        <a href="{{ $detailUrl }}" class="block truncate text-[14px] font-medium text-primary after:absolute after:inset-0">{{ $generation->subject ?? __('Sujet choisi par l\'IA') }}</a>
        <p class="mt-0.5 truncate text-[12px] text-muted">
            @if ($generation->outcome)
                <span class="text-secondary">{{ $generation->outcome }}</span>,
            @endif
            {{ $generation->trigger->label() }}, {{ $generation->createdAt->format('d/m/Y H:i') }} (UTC)
        </p>
    </div>
    @if ($generation->duration)
        <span class="hidden shrink-0 text-[13px] tabular-nums text-muted md:block" data-generation-duration>{{ $generation->duration }}</span>
    @endif
    <span class="hidden shrink-0 text-[13px] tabular-nums text-secondary sm:block">{{ $generation->cost }}</span>
    <x-admin.blog.generation-status :status="$generation->status" class="shrink-0" />
    <div class="relative z-10 flex shrink-0 items-center" data-generation-actions>
        @if ($generation->isRetryable())
            <x-ui::tooltip :text="__('Relancer')">
                <button type="button" class="{{ $iconButton }}" aria-label="{{ __('Relancer') }}" data-retry-generation
                    wire:click="retry({{ $generation->id }})" wire:loading.attr="disabled" wire:target="retry({{ $generation->id }})">
                    <x-ui::icon name="arrow-path" class="h-[18px] w-[18px]" wire:loading.class="animate-spin" wire:target="retry({{ $generation->id }})" />
                </button>
            </x-ui::tooltip>
        @elseif ($stoppable && $generation->isCancellable())
            <x-ui::tooltip :text="__('Arrêter')">
                <button type="button" class="{{ $iconButton }}" aria-label="{{ __('Arrêter') }}" data-stop-generation
                    x-on:click="pending = {{ $generation->id }}; $dispatch('ui-open-modal', 'stop-generation')">
                    <x-ui::icon name="stop" class="h-[18px] w-[18px]" />
                </button>
            </x-ui::tooltip>
        @elseif ($generation->postId)
            <x-ui::tooltip :text="__('Ouvrir le brouillon')">
                <a href="{{ route('admin.blog.posts.edit', ['post' => $generation->postId]) }}" class="{{ $iconButton }}" aria-label="{{ __('Ouvrir le brouillon') }}" data-open-draft>
                    <x-ui::icon name="document-text" class="h-[18px] w-[18px]" />
                </a>
            </x-ui::tooltip>
        @elseif ($generation->sourcePostId)
            {{-- Une demande d'image ou de traduction mene a l'article depuis lequel elle a ete faite (24/11/2026). --}}
            <x-ui::tooltip :text="__('Ouvrir l\'article')">
                <a href="{{ route('admin.blog.posts.edit', ['post' => $generation->sourcePostId]) }}" class="{{ $iconButton }}" aria-label="{{ __('Ouvrir l\'article') }}" data-open-source-post>
                    <x-ui::icon name="document-text" class="h-[18px] w-[18px]" />
                </a>
            </x-ui::tooltip>
        @endif
    </div>
</li>
