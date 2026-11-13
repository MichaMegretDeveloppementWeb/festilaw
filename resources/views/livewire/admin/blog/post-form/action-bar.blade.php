{{-- Barre d'actions du formulaire d'article : elle reste en bas de l'ecran pendant qu'on fait defiler.
     UN seul « Enregistrer » pour tout l'article. « Publier » enregistre d'abord.
     Sur telephone : Réglages, Enregistrer, Publier (l'apercu est dans le menu « Autres actions »). --}}
<div class="sticky bottom-0 z-30 -mx-4 -mb-6 mt-auto border-t border-default bg-surface px-4 py-3 sm:-mx-6 sm:-mb-8 sm:px-6">
    <p x-show="dirty" x-cloak class="mb-2 flex items-center gap-1.5 text-[12px] font-medium text-amber-700 sm:hidden dark:text-amber-400">
        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> {{ __('Modifications non enregistrées') }}
    </p>

    <div class="flex items-center gap-2">
        <p class="mr-auto hidden min-w-0 items-center gap-1.5 text-[12px] sm:flex" aria-live="polite">
            <span x-show="dirty" x-cloak class="flex items-center gap-1.5 font-medium text-amber-700 dark:text-amber-400">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> {{ __('Modifications non enregistrées') }}
            </span>
            <span x-show="! dirty" class="text-muted">{{ $postId === null ? __('Rien n\'est encore enregistré') : __('Tout est enregistré') }}</span>
        </p>

        <x-ui::button variant="secondary" class="flex-1 sm:flex-none lg:hidden" x-on:click="settingsOpen = true" aria-controls="post-settings">
            <x-ui::icon name="cog-6-tooth" /> {{ __('Réglages') }}
        </x-ui::button>

        {{-- Une seule facon de voir l'article : l'apercu tant qu'il n'est pas en ligne, la page publique ensuite. --}}
        <x-ui::dropdown align="right" width="w-56" class="hidden sm:inline-block">
            <x-slot:trigger>
                <x-ui::button variant="secondary" x-bind:disabled="busy" aria-haspopup="menu">
                    <x-ui::icon :name="$isPublished ? 'arrow-top-right-on-square' : 'eye'" />
                    {{ $isPublished ? __('Voir la page') : __('Aperçu') }}
                    <x-ui::icon name="chevron-down" class="h-3 w-3" stroke-width="2.5" />
                </x-ui::button>
            </x-slot:trigger>

            @if ($isPublished && $view->publicUrls !== [])
                @foreach ($view->publicUrls as $locale => $url)
                    <x-ui::dropdown-item :href="$url" target="_blank" rel="noopener">{{ ucfirst($locales[$locale] ?? $locale) }}</x-ui::dropdown-item>
                @endforeach
            @else
                @foreach ($locales as $locale => $language)
                    <x-ui::dropdown-item x-on:click="preview('{{ $locale }}')">{{ ucfirst($language) }}</x-ui::dropdown-item>
                @endforeach
            @endif
        </x-ui::dropdown>

        <x-ui::button :variant="$isPublished ? 'primary' : 'secondary'" class="flex-1 sm:flex-none" x-on:click="save()" x-bind:disabled="busy" :loading="true" target="save">
            {{ __('Enregistrer') }}
        </x-ui::button>

        @unless ($isPublished)
            <x-ui::button class="flex-1 sm:flex-none" x-on:click="publish()" x-bind:disabled="busy" :loading="true" target="publish">
                {{ __('Publier') }}
            </x-ui::button>
        @endunless
    </div>
</div>
