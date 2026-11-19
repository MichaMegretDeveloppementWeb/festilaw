{{-- Liste des dossiers (ou des prises de contact), sur page blanche : la recherche et les filtres sur une ligne,
     puis le tableau sans carte, sur toute la largeur. Toute la ligne ouvre le dossier. Sous 768 px, les deux
     filtres passent dans un panneau flottant ouvert par le bouton « Filtres », comme sur la liste des articles :
     la liste reste en haut de l'ecran du telephone. --}}
@php
    $activeFilters = ($state !== '' ? 1 : 0) + ($type !== '' ? 1 : 0);
@endphp
<div x-data="{ filtersOpen: false }">
    <x-ui::page-header
        :title="$contactsMode ? __('Prises de contact') : __('Dossiers')"
        :description="$contactsMode ? __('Messages reçus via le formulaire de contact.') : __('Tous les dossiers clients, du premier contact à la finalisation.')"
        class="mb-8" />

    <div class="mb-4 flex items-center gap-3">
        <x-ui::search-input id="f-search" wire:model.live.debounce.400ms="search" wrapper-class="min-w-0 flex-1"
            :placeholder="$contactsMode ? __('Nom, email...') : __('Email, entreprise, référence...')"
            aria-label="{{ $contactsMode ? __('Rechercher une prise de contact') : __('Rechercher un dossier') }}" />
        @unless ($contactsMode)
            <div class="relative shrink-0 md:static" x-on:click.outside="filtersOpen = false" x-on:keydown.escape.window="filtersOpen = false">
                <x-ui::button variant="secondary" class="md:hidden" x-on:click="filtersOpen = ! filtersOpen"
                    x-bind:aria-expanded="filtersOpen.toString()" aria-controls="submission-filters" aria-haspopup="dialog">
                    <x-ui::icon name="adjustments-horizontal" />
                    <span>{{ __('Filtres') }}</span>
                    @if ($activeFilters > 0)
                        <span class="flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-gray-900 px-1 text-[10px] font-semibold text-white dark:bg-white dark:text-gray-900">{{ $activeFilters }}</span>
                    @endif
                </x-ui::button>

                <div id="submission-filters" x-bind:data-open="filtersOpen.toString()"
                    class="absolute right-0 top-full z-30 mt-2 hidden w-72 max-w-[calc(100vw-2rem)] flex-col gap-3 rounded-xl bg-surface p-4 shadow-[0_10px_40px_-8px_rgb(0_0_0/0.25)] data-[open=true]:flex md:static md:z-auto md:mt-0 md:flex md:w-auto md:max-w-none md:flex-row md:rounded-none md:bg-transparent md:p-0 md:shadow-none dark:ring-1 dark:ring-white/10 md:dark:ring-0">
                    <p class="text-[13px] font-semibold text-primary md:hidden">{{ __('Filtres') }}</p>
                    <x-ui::select id="f-state" wire:model.live="state" class="md:w-52" aria-label="{{ __('Filtrer par état') }}"
                        :options="collect($stateFilters)->prepend(__('Tous les états'), '')->all()" />
                    <x-ui::select id="f-type" wire:model.live="type" class="md:w-52" aria-label="{{ __('Filtrer par parcours') }}"
                        :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->prepend(__('Tous les parcours'), '')->all()" />
                </div>
            </div>
        @endunless
    </div>

    @error('submissions-load-failed')
        <x-ui::load-failure :title="$message" :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
    @else
    <div class="relative border-t border-default">
        @if ($submissions === null || $submissions->isEmpty())
            <x-ui::empty-state icon="inbox" :title="$contactsMode ? __('Aucune prise de contact.') : __('Aucun dossier ne correspond.')" />
        @else
            <x-ui::table>
                <x-ui::table.head>
                    @if ($contactsMode)
                        <x-ui::table.header-cell :first="true">{{ __('Nom') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Email') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Message') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell :last="true">{{ __('Reçu le') }}</x-ui::table.header-cell>
                    @else
                        <x-ui::table.header-cell :first="true">{{ __('Référence') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Parcours') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('État') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Client') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell>{{ __('Email') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell :last="true">{{ __('Date') }}</x-ui::table.header-cell>
                    @endif
                </x-ui::table.head>
                <x-ui::table.body>
                    @foreach ($submissions as $submission)
                        <x-ui::table.row wire:key="submission-{{ $submission->id }}" wire:click="show({{ $submission->id }})">
                            @if ($contactsMode)
                                <x-ui::table.cell :first="true" variant="primary" class="font-medium">{{ $submission->first_name ?: '-' }}</x-ui::table.cell>
                                <x-ui::table.cell>{{ $submission->email }}</x-ui::table.cell>
                                <x-ui::table.cell variant="muted" :nowrap="false"><span class="block min-w-[12rem] max-w-xs truncate">{{ $submission->message }}</span></x-ui::table.cell>
                                <x-ui::table.cell :last="true" variant="muted">{{ $submission->created_at->format('d/m/Y') }}</x-ui::table.cell>
                            @else
                                <x-ui::table.cell :first="true" variant="primary" class="font-medium">{{ $submission->reference }}</x-ui::table.cell>
                                <x-ui::table.cell>{{ $submission->type->label() }}</x-ui::table.cell>
                                <x-ui::table.cell><x-admin.dossier-state-badge :state="$dossierStates[$submission->id]" /></x-ui::table.cell>
                                <x-ui::table.cell>{{ $submission->company_name ?: (trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: '-') }}</x-ui::table.cell>
                                <x-ui::table.cell>{{ $submission->email }}</x-ui::table.cell>
                                <x-ui::table.cell :last="true" variant="muted">{{ $submission->created_at->format('d/m/Y') }}</x-ui::table.cell>
                            @endif
                        </x-ui::table.row>
                    @endforeach
                </x-ui::table.body>
            </x-ui::table>

            <x-ui::pagination :paginator="$submissions" mode="livewire" />
        @endif
    </div>
    @enderror
</div>
