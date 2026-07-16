<div>
    <a href="{{ route('admin.submissions.index') }}" class="admin-back">&larr; {{ __('Retour aux dossiers') }}</a>

    <h1 class="admin-page-title">
        {{ $submission->reference }}
        <span style="font-weight: 500; color: var(--color-ink-soft); font-size: 16px;">· {{ $submission->type->label() }}</span>
    </h1>

    @if (session('admin_flash'))
        <div class="admin-flash">{{ session('admin_flash') }}</div>
    @endif

    {{-- Statut + changement --}}
    <div class="admin-card">
        <div class="admin-card__title">{{ __('Statut') }}</div>
        <div style="display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap;">
            <div><x-admin.status-badge :status="$submission->status" /></div>
            <form wire:submit="updateStatus" style="display: flex; align-items: flex-end; gap: 10px;">
                <div class="admin-field">
                    <label class="admin-field__label" for="new-status">{{ __('Changer le statut') }}</label>
                    <select id="new-status" class="admin-select" wire:model="newStatus">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="admin-btn admin-btn--dark admin-btn--sm">{{ __('Enregistrer') }}</button>
            </form>
        </div>
        @error('newStatus') <p class="admin-error">{{ $message }}</p> @enderror
    </div>

    {{-- Infos client --}}
    <div class="admin-card">
        <div class="admin-card__title">{{ __('Client') }}</div>
        <dl class="admin-dl">
            <dt>{{ __('Entreprise') }}</dt><dd>{{ $submission->company_name ?: '-' }}</dd>
            <dt>{{ __('Contact') }}</dt><dd>{{ trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: '-' }}</dd>
            <dt>{{ __('Email') }}</dt><dd><a href="mailto:{{ $submission->email }}">{{ $submission->email }}</a></dd>
            <dt>{{ __('Téléphone') }}</dt><dd>{{ $submission->phone ?: '-' }}</dd>
            <dt>{{ __('Site web') }}</dt><dd>{{ $submission->website_url ?: '-' }}</dd>
            <dt>{{ __('N° immatriculation') }}</dt><dd>{{ $submission->company_registration_number ?: '-' }}</dd>
            <dt>{{ __('Langue') }}</dt><dd>{{ strtoupper((string) $submission->locale) }}</dd>
            <dt>{{ __('Créé le') }}</dt><dd>{{ $submission->created_at->format('d/m/Y H:i') }}</dd>
        </dl>
    </div>

    {{-- Contrat / signature --}}
    @if ($submission->contract)
        <div class="admin-card">
            <div class="admin-card__title">{{ __('Mandat / signature') }}</div>
            <dl class="admin-dl">
                <dt>{{ __('Statut signature') }}</dt><dd>{{ $submission->contract->signature_status->value }}</dd>
                <dt>{{ __('Prestataire') }}</dt><dd>{{ $submission->contract->signature_provider ?: '-' }}</dd>
            </dl>
            @if ($submission->contract->signed_file_path)
                <ul class="admin-files" style="margin-top: 12px;">
                    <li>
                        {{ __('Mandat signé') }}
                        <a href="{{ route('admin.submissions.mandate', ['submission' => $submission->id]) }}">{{ __('Télécharger') }}</a>
                    </li>
                </ul>
            @endif
        </div>
    @endif

    {{-- Pièces --}}
    <div class="admin-card">
        <div class="admin-card__title">{{ __('Pièces') }} ({{ $submission->uploadedDocuments->count() }})</div>
        @if ($submission->uploadedDocuments->isEmpty())
            <p style="color: var(--color-ink-soft); font-size: 14px;">{{ __('Aucune pièce téléversée.') }}</p>
        @else
            <ul class="admin-files">
                @foreach ($submission->uploadedDocuments as $doc)
                    <li wire:key="doc-{{ $doc->id }}">
                        {{ $doc->type->label() }} <span style="color: var(--color-ink-soft);">· {{ $doc->original_filename }}</span>
                        <a href="{{ route('admin.submissions.document', ['submission' => $submission->id, 'document' => $doc->id]) }}">{{ __('Télécharger') }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Paiements --}}
    @if ($submission->payments->isNotEmpty())
        <div class="admin-card">
            <div class="admin-card__title">{{ __('Paiements') }}</div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>{{ __('Montant') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Prestataire') }}</th>
                        <th>{{ __('Payé le') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($submission->payments as $payment)
                        <tr wire:key="pay-{{ $payment->id }}">
                            <td>{{ number_format($payment->amount_cents / 100, 2, ',', ' ') }} {{ $payment->currency }}</td>
                            <td>{{ $payment->status->value }}</td>
                            <td>{{ $payment->provider }}</td>
                            <td>{{ $payment->paid_at?->format('d/m/Y H:i') ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- RDV (Scale) --}}
    @if ($submission->appointment)
        <div class="admin-card">
            <div class="admin-card__title">{{ __('Rendez-vous') }}</div>
            <dl class="admin-dl">
                <dt>{{ __('Programmé le') }}</dt><dd>{{ $submission->appointment->scheduled_at?->format('d/m/Y H:i') ?: '-' }}</dd>
                <dt>{{ __('Statut') }}</dt><dd>{{ $submission->appointment->status->value }}</dd>
            </dl>
        </div>
    @endif
</div>
