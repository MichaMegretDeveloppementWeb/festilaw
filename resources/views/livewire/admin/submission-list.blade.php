{{-- Liste des dossiers (ou des prises de contact), sur page blanche : la recherche et les filtres sur une ligne,
     puis le tableau sans carte, sur toute la largeur. Toute la ligne ouvre le dossier. --}}
<div>
    <x-ui::page-header
        :title="$contactsMode ? __('Prises de contact') : __('Dossiers')"
        :description="$contactsMode ? __('Messages reçus via le formulaire de contact.') : __('Tous les dossiers clients, du premier contact à la finalisation.')"
        class="mb-8" />

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui::search-input id="f-search" wire:model.live.debounce.400ms="search" wrapper-class="min-w-[240px] flex-1"
            :placeholder="$contactsMode ? __('Nom, email...') : __('Email, entreprise, référence...')"
            aria-label="{{ $contactsMode ? __('Rechercher une prise de contact') : __('Rechercher un dossier') }}" />
        @unless ($contactsMode)
            <x-ui::select id="f-state" wire:model.live="state" class="sm:w-52" aria-label="{{ __('Filtrer par état') }}"
                :options="collect($stateFilters)->prepend(__('Tous les états'), '')->all()" />
            <x-ui::select id="f-type" wire:model.live="type" class="sm:w-52" aria-label="{{ __('Filtrer par parcours') }}"
                :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->prepend(__('Tous les parcours'), '')->all()" />
        @endunless
    </div>

    @error('submissions-load-failed')
        <x-ui::load-failure :title="$message" :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="$refresh" />
    @else
    <div class="relative -mx-4 border-t border-default sm:-mx-5">
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
                                <x-ui::table.cell variant="muted" :nowrap="false"><span class="block max-w-xs truncate">{{ $submission->message }}</span></x-ui::table.cell>
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
