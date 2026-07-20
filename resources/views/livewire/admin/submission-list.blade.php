<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ $contactsMode ? __('Prises de contact') : __('Dossiers') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $contactsMode ? __('Messages reçus via le formulaire de contact.') : __('Tous les dossiers clients, du premier contact à la finalisation.') }}</p>
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[240px] flex-1">
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="f-search">{{ __('Recherche') }}</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input id="f-search" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ $contactsMode ? __('Nom, email...') : __('Email, entreprise, référence...') }}"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
            </div>
        </div>
        @unless ($contactsMode)
            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="f-status">{{ __('Statut') }}</label>
                <select id="f-status" wire:model.live="status"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    <option value="">{{ __('Tous') }}</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="f-type">{{ __('Parcours') }}</label>
                <select id="f-type" wire:model.live="type"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    <option value="">{{ __('Tous') }}</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endunless
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($submissions->isEmpty())
            <p class="px-6 py-16 text-center text-sm text-slate-500">{{ $contactsMode ? __('Aucune prise de contact.') : __('Aucun dossier ne correspond.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            @if ($contactsMode)
                                <th class="px-4 py-3">{{ __('Nom') }}</th>
                                <th class="px-4 py-3">{{ __('Email') }}</th>
                                <th class="px-4 py-3">{{ __('Message') }}</th>
                                <th class="px-4 py-3">{{ __('Reçu le') }}</th>
                            @else
                                <th class="px-4 py-3">{{ __('Référence') }}</th>
                                <th class="px-4 py-3">{{ __('Parcours') }}</th>
                                <th class="px-4 py-3">{{ __('Statut') }}</th>
                                <th class="px-4 py-3">{{ __('Client') }}</th>
                                <th class="px-4 py-3">{{ __('Email') }}</th>
                                <th class="px-4 py-3">{{ __('Date') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($submissions as $submission)
                            <tr wire:key="submission-{{ $submission->id }}" wire:click="show({{ $submission->id }})"
                                class="cursor-pointer transition hover:bg-slate-50">
                                @if ($contactsMode)
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $submission->first_name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $submission->email }}</td>
                                    <td class="max-w-xs truncate px-4 py-3 text-slate-500">{{ $submission->message }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $submission->created_at->format('d/m/Y') }}</td>
                                @else
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $submission->reference }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $submission->type->label() }}</td>
                                    <td class="px-4 py-3"><x-admin.status-badge :status="$submission->status" /></td>
                                    <td class="px-4 py-3 text-slate-600">{{ $submission->company_name ?: (trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: '-') }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $submission->email }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $submission->created_at->format('d/m/Y') }}</td>
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
