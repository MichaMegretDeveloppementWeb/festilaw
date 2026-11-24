{{-- En-tete du formulaire d'article : retour a la liste, titre de l'article, statut (un menu : publier,
     programmer, depublier), ce qui reste a completer, puis la suppression (un bouton, ou le menu des
     autres actions sur telephone).
     $status, $isPublished, $isScheduled, $blockerCount, $warningCount, $heading : poses par post-form. --}}
<div class="mb-6">
    <a href="{{ route('admin.blog.posts.index') }}" class="inline-flex items-center gap-1.5 text-[13px] text-muted transition-colors hover:text-primary">
        <x-ui::icon name="arrow-left" class="h-3.5 w-3.5" /> {{ __('Articles') }}
    </a>

    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2">
        <h1 class="min-w-0 max-w-full truncate text-[1.375rem] font-semibold leading-7 tracking-[-0.01em] text-primary"
            x-text="text('fr', 'title') || text('en', 'title') || text('es', 'title') || @js($postId === null ? __('Nouvel article') : __('Article sans titre'))">{{ $heading }}</h1>

        {{-- Statut : la pastille ouvre les changements de statut possibles. --}}
        <x-ui::dropdown width="w-64">
            <x-slot:trigger>
                <button type="button" aria-haspopup="menu" aria-label="{{ __('Statut : :status. Changer le statut', ['status' => $status->label()]) }}"
                    @class([
                        'inline-flex items-center gap-1 rounded-full py-1 pl-2.5 pr-2 text-[12px] font-medium transition-colors',
                        'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400' => $isPublished,
                        'bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-500/10 dark:text-blue-400' => $isScheduled,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' => ! $isPublished && ! $isScheduled,
                    ])>
                    {{ $status->label() }}
                    <x-ui::icon name="chevron-down" class="h-3 w-3" stroke-width="2.5" />
                </button>
            </x-slot:trigger>

            @unless ($isPublished)
                <x-ui::dropdown-item icon="arrow-up-circle" x-on:click="publish()">{{ __('Publier maintenant') }}</x-ui::dropdown-item>
                <x-ui::dropdown-item icon="clock" x-on:click="$dispatch('ui-open-modal', 'schedule-post')">
                    {{ $isScheduled ? __('Changer la date de publication') : __('Programmer la publication') }}
                </x-ui::dropdown-item>
            @endunless
            @if ($isScheduled)
                <x-ui::dropdown-item icon="x-circle" x-on:click="$dispatch('ui-open-modal', 'unpublish-post')">{{ __('Annuler la programmation') }}</x-ui::dropdown-item>
            @endif
            @if ($isPublished)
                <x-ui::dropdown-item icon="eye-slash" x-on:click="$dispatch('ui-open-modal', 'unpublish-post')">{{ __('Dépublier') }}</x-ui::dropdown-item>
            @endif
        </x-ui::dropdown>

        {{-- Ce qui reste a completer, d'apres le dernier enregistrement. Rien sur un article publie et complet. --}}
        @if ($view !== null && $blockerCount > 0)
            <button type="button" x-on:click="$dispatch('ui-open-modal', 'post-readiness')"
                class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[12px] font-medium text-amber-700 transition-colors hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-400">
                {{ trans_choice('{1} 1 point à compléter|[2,*] :count points à compléter', $blockerCount, ['count' => $blockerCount]) }}
            </button>
        @elseif ($view !== null && ! $isPublished && $warningCount > 0)
            <button type="button" x-on:click="$dispatch('ui-open-modal', 'post-readiness')"
                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[12px] font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400">
                {{ __('Prêt à publier') }} · {{ trans_choice('{1} 1 conseil|[2,*] :count conseils', $warningCount, ['count' => $warningCount]) }}
            </button>
        @elseif ($view !== null && ! $isPublished)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[12px] font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                <x-ui::icon name="check" class="h-3 w-3" stroke-width="2.5" /> {{ __('Prêt à publier') }}
            </span>
        @endif

        @if ($isPublished && $view->publishedAt)
            <span class="text-[12px] text-muted">{{ __('Publié le :date (UTC)', ['date' => $view->publishedAt->format('d/m/Y à H:i')]) }}</span>
        @elseif ($isScheduled && $view->scheduledFor)
            <span class="text-[12px] text-muted">{{ __('Publication prévue le :date (UTC)', ['date' => $view->scheduledFor->format('d/m/Y à H:i')]) }}</span>
        @elseif ($postId === null)
            <span class="text-[12px] text-muted">{{ __('Pas encore enregistré') }}</span>
        @endif

        {{-- A partir de 640 px, la barre du bas porte deja « Voir la page » ou « Aperçu » : il ne reste ici
             que la suppression, en bouton. En dessous, la barre ne les a pas, et le menu les garde. --}}
        @if ($postId !== null)
            {{-- Texte et icone rouges, sans contour ni fond, un fond rouge tres clair au survol, a la taille des autres
                 boutons (Micha, 24/11). Le kit n'a pas cette variante (seul `danger`, en rouge plein) : retouche locale
                 du bouton `ghost`, a retirer le jour ou il la propose. --}}
            <x-ui::button variant="ghost" class="ml-auto hidden text-red-600 hover:bg-red-50 hover:text-red-700 sm:inline-flex dark:text-red-400 dark:hover:bg-red-500/10 dark:hover:text-red-300" data-delete-post
                x-on:click="$dispatch('ui-open-modal', 'delete-post')">
                <x-ui::icon name="trash" /> {{ __('Supprimer') }}
            </x-ui::button>
        @endif

        {{-- Autres actions, sur telephone : voir l'article (apercu, ou pages en ligne une fois publie), supprimer. --}}
        <x-ui::dropdown align="right" width="w-60" class="ml-auto sm:hidden">
            <x-slot:trigger>
                <button type="button" aria-haspopup="menu" aria-label="{{ __('Autres actions') }}"
                    class="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-secondary transition-colors hover:bg-elevated hover:text-primary sm:min-h-0 sm:min-w-0 sm:p-1.5">
                    <x-ui::icon name="ellipsis-horizontal" class="h-5 w-5" />
                </button>
            </x-slot:trigger>

            @if ($isPublished && $view->publicUrls !== [])
                @foreach ($view->publicUrls as $locale => $url)
                    <x-ui::dropdown-item icon="arrow-top-right-on-square" :href="$url" target="_blank" rel="noopener">{{ __('Voir la page en :language', ['language' => $locales[$locale] ?? $locale]) }}</x-ui::dropdown-item>
                @endforeach
            @else
                @foreach ($locales as $locale => $language)
                    <x-ui::dropdown-item icon="eye" x-on:click="preview('{{ $locale }}')">{{ __('Aperçu en :language', ['language' => $language]) }}</x-ui::dropdown-item>
                @endforeach
            @endif

            @if ($postId !== null)
                <x-ui::dropdown-item :separator="true" />
                <x-ui::dropdown-item icon="trash" :danger="true" x-on:click="$dispatch('ui-open-modal', 'delete-post')">{{ __('Supprimer l\'article') }}</x-ui::dropdown-item>
            @endif
        </x-ui::dropdown>
    </div>
</div>
