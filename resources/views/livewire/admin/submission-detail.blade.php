<div>
    <a href="{{ route('admin.submissions.index') }}" class="admin-back">&larr; {{ __('Retour aux dossiers') }}</a>

    <div class="admin-detail__head">
        <div>
            <div class="admin-detail__ref">{{ $submission->reference }}</div>
            <div class="admin-detail__sub">{{ $submission->type->label() }} · {{ __('Créé le') }} {{ $submission->created_at->format('d/m/Y à H:i') }}</div>
        </div>
        <x-admin.status-badge :status="$submission->status" />
    </div>

    @if (session('admin_flash'))
        <div class="admin-flash">{{ session('admin_flash') }}</div>
    @endif

    <div class="admin-detail__grid">
        <div class="admin-detail__main">
            {{-- Client --}}
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
                </dl>
            </div>

            {{-- Message (Contact) --}}
            @if ($submission->message)
                <div class="admin-card">
                    <div class="admin-card__title">{{ __('Message') }}</div>
                    <p style="white-space: pre-wrap; line-height: 1.6; font-size: 14px; margin: 0;">{{ $submission->message }}</p>
                </div>
            @endif

            {{-- Mandat / signature --}}
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
                    <p style="color: var(--color-ink-soft); font-size: 14px; margin: 0;">{{ __('Aucune pièce téléversée.') }}</p>
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

        {{-- Panneau d'actions --}}
        <aside class="admin-detail__aside">
            <div class="admin-card">
                <div class="admin-card__title">{{ __('Statut du dossier') }}</div>
                <div style="margin-bottom: 16px;"><x-admin.status-badge :status="$submission->status" /></div>
                <form wire:submit="updateStatus">
                    <div class="admin-field" style="margin-bottom: 14px;">
                        <label class="admin-field__label" for="new-status">{{ __('Changer le statut') }}</label>
                        <select id="new-status" class="admin-select" wire:model="newStatus" style="width: 100%;">
                            @foreach ($statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="admin-btn admin-btn--dark admin-btn--sm" style="width: 100%;" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="updateStatus">{{ __('Enregistrer le statut') }}</span>
                        <span wire:loading wire:target="updateStatus">{{ __('Enregistrement') }}&hellip;</span>
                    </button>
                    @error('newStatus') <p class="admin-error">{{ $message }}</p> @enderror
                </form>
            </div>

            <div class="admin-card">
                <div class="admin-card__title">{{ __('Contacter le client') }}</div>
                <a href="mailto:{{ $submission->email }}" class="admin-btn admin-btn--primary admin-btn--sm" style="width: 100%;">{{ __('Envoyer un email') }}</a>
            </div>
        </aside>
    </div>
</div>
