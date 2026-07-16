<div>
    <h1 class="admin-page-title">{{ __('Dossiers') }}</h1>

    <div class="admin-filters">
        <div class="admin-field">
            <label class="admin-field__label" for="f-search">{{ __('Recherche') }}</label>
            <input id="f-search" type="search" class="admin-input" wire:model.live.debounce.400ms="search" placeholder="{{ __('Email, entreprise, référence...') }}">
        </div>
        <div class="admin-field">
            <label class="admin-field__label" for="f-status">{{ __('Statut') }}</label>
            <select id="f-status" class="admin-select" wire:model.live="status">
                <option value="">{{ __('Tous') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="admin-field">
            <label class="admin-field__label" for="f-type">{{ __('Parcours') }}</label>
            <select id="f-type" class="admin-select" wire:model.live="type">
                <option value="">{{ __('Tous') }}</option>
                @foreach ($types as $t)
                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="admin-card">
        @if ($submissions->isEmpty())
            <p class="admin-empty">{{ __('Aucun dossier ne correspond.') }}</p>
        @else
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('Référence') }}</th>
                            <th>{{ __('Parcours') }}</th>
                            <th>{{ __('Statut') }}</th>
                            <th>{{ __('Client') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($submissions as $submission)
                            <tr wire:key="submission-{{ $submission->id }}">
                                <td>{{ $submission->reference }}</td>
                                <td>{{ $submission->type->label() }}</td>
                                <td><x-admin.status-badge :status="$submission->status" /></td>
                                <td>{{ $submission->company_name ?: (trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: '-') }}</td>
                                <td>{{ $submission->email }}</td>
                                <td>{{ $submission->created_at->format('d/m/Y') }}</td>
                                <td><a class="admin-table__link" href="{{ route('admin.submissions.show', ['submission' => $submission->id]) }}">{{ __('Voir') }} →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $submissions->links('pagination.admin') }}
</div>
