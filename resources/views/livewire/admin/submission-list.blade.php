<div>
    <x-ui.page-header
        title="{{ $contactsMode ? __('Prises de contact') : __('Dossiers') }}"
        description="{{ $contactsMode ? __('Messages reçus via le formulaire de contact.') : __('Tous les dossiers clients, du premier contact à la finalisation.') }}"
        class="mb-6" />

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[240px] flex-1">
            <x-ui.form-group label="{{ __('Recherche') }}" for="f-search">
                <x-ui.search-input id="f-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="{{ $contactsMode ? __('Nom, email...') : __('Email, entreprise, référence...') }}" />
            </x-ui.form-group>
        </div>
        @unless ($contactsMode)
            <x-ui.form-group label="{{ __('État') }}" for="f-state">
                <x-ui.select id="f-state" wire:model.live="state" :options="collect($stateFilters)->prepend(__('Tous'), '')->all()" />
            </x-ui.form-group>
            <x-ui.form-group label="{{ __('Parcours') }}" for="f-type">
                <x-ui.select id="f-type" wire:model.live="type" :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->prepend(__('Tous'), '')->all()" />
            </x-ui.form-group>
        @endunless
    </div>

    <div class="overflow-hidden rounded-xl border border-base bg-surface">
        @if ($submissions->isEmpty())
            <p class="px-6 py-16 text-center text-[13px] text-secondary">{{ $contactsMode ? __('Aucune prise de contact.') : __('Aucun dossier ne correspond.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-base bg-elevated text-left text-[11px] font-semibold uppercase tracking-wide text-muted">
                            @if ($contactsMode)
                                <th class="px-4 py-3">{{ __('Nom') }}</th>
                                <th class="px-4 py-3">{{ __('Email') }}</th>
                                <th class="px-4 py-3">{{ __('Message') }}</th>
                                <th class="px-4 py-3">{{ __('Reçu le') }}</th>
                            @else
                                <th class="px-4 py-3">{{ __('Référence') }}</th>
                                <th class="px-4 py-3">{{ __('Parcours') }}</th>
                                <th class="px-4 py-3">{{ __('État') }}</th>
                                <th class="px-4 py-3">{{ __('Client') }}</th>
                                <th class="px-4 py-3">{{ __('Email') }}</th>
                                <th class="px-4 py-3">{{ __('Date') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($submissions as $submission)
                            <tr wire:key="submission-{{ $submission->id }}" wire:click="show({{ $submission->id }})"
                                class="cursor-pointer transition hover:bg-elevated">
                                @if ($contactsMode)
                                    <td class="px-4 py-3 font-medium text-primary">{{ $submission->first_name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-secondary">{{ $submission->email }}</td>
                                    <td class="max-w-xs truncate px-4 py-3 text-muted">{{ $submission->message }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $submission->created_at->format('d/m/Y') }}</td>
                                @else
                                    <td class="px-4 py-3 font-medium text-primary">{{ $submission->reference }}</td>
                                    <td class="px-4 py-3 text-secondary">{{ $submission->type->label() }}</td>
                                    <td class="px-4 py-3"><x-admin.dossier-state-badge :state="$dossierStates[$submission->id]" /></td>
                                    <td class="px-4 py-3 text-secondary">{{ $submission->company_name ?: (trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: '-') }}</td>
                                    <td class="px-4 py-3 text-secondary">{{ $submission->email }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $submission->created_at->format('d/m/Y') }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $submissions->links('pagination.admin') }}
</div>
