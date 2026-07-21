<div x-data="{ emailOpen: false }" @email-sent.window="emailOpen = false">
    <nav class="mb-4 flex items-center gap-1.5 text-sm text-slate-500">
        <a href="{{ $isContact ? route('admin.contacts.index') : route('admin.submissions.index') }}" class="transition hover:text-slate-700">{{ $isContact ? __('Prises de contact') : __('Dossiers') }}</a>
        <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="font-medium text-slate-700">{{ $isContact ? __('Prise de contact') : $submission->reference }}</span>
    </nav>

    @if ($isContact)
        <div class="mb-6">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
                {{ __('Prise de contact') }}
            </span>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $submission->first_name ?: __('Contact sans nom') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Reçue le') }} {{ $submission->created_at->format('d/m/Y à H:i') }} · {{ __('via le formulaire de contact') }} · {{ __('Réf.') }} {{ $submission->reference }}</p>
        </div>
    @else
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $submission->reference }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $submission->type->label() }} · {{ __('Créé le') }} {{ $submission->created_at->format('d/m/Y à H:i') }}</p>
            </div>
            <x-admin.status-badge :status="$submission->status" />
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $isContact ? __('Coordonnées') : __('Client') }}</h2>
                </div>
                <div class="px-5 py-2">
                    <dl class="divide-y divide-slate-100 text-sm">
                        @if ($isContact)
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Nom') }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ $submission->first_name ?: '-' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Email') }}</dt>
                                <dd class="text-right font-medium text-brand-700"><a href="mailto:{{ $submission->email }}" class="hover:underline">{{ $submission->email }}</a></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Site web') }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ $submission->website_url ?: '-' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Langue') }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ strtoupper((string) $submission->locale) }}</dd>
                            </div>
                        @else
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Entreprise') }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ $submission->company_name ?: '-' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Contact') }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: '-' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Email') }}</dt>
                                <dd class="text-right font-medium text-brand-700"><a href="mailto:{{ $submission->email }}" class="hover:underline">{{ $submission->email }}</a></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Téléphone') }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ $submission->phone ?: '-' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Site web') }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ $submission->website_url ?: '-' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('N° immatriculation') }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ $submission->company_registration_number ?: '-' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Langue du dossier') }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ strtoupper((string) $submission->locale) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            @if ($submission->message)
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Message') }}</h2>
                    </div>
                    <div class="p-5">
                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ $submission->message }}</p>
                    </div>
                </section>
            @endif

            @if ($submission->contract)
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Mandat / signature') }}</h2>
                    </div>
                    <div class="px-5 py-2">
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Statut de signature') }}</dt>
                                <dd class="font-medium text-slate-900">{{ $submission->contract->signature_status->label() }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Prestataire') }}</dt>
                                <dd class="font-medium text-slate-900">{{ $submission->contract->signature_provider ?: '-' }}</dd>
                            </div>
                        </dl>
                        @if ($submission->contract->signed_file_path)
                            <a href="{{ route('admin.submissions.mandate', ['submission' => $submission->id]) }}"
                                class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-brand-700 hover:underline">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                {{ __('Télécharger le mandat signé') }}
                            </a>
                        @endif

                        {{-- Contrat contresigne par Festilaw (contre-signature manuelle, hors SignWell) --}}
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Contrat contresigné') }}</p>
                            @if ($submission->contract->countersigned_file_path)
                                <a href="{{ route('admin.submissions.countersigned', ['submission' => $submission->id]) }}"
                                    class="mt-2 inline-flex items-center gap-2 text-sm font-medium text-brand-700 hover:underline">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    {{ __('Télécharger le contrat contresigné') }}
                                </a>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Déposé le') }} {{ $submission->contract->countersigned_at?->format('d/m/Y H:i') }}</p>
                            @endif

                            <form wire:submit="uploadCountersigned" class="mt-3 space-y-2">
                                <input type="file" wire:model="countersigned" accept="application/pdf"
                                    class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                                <div wire:loading wire:target="countersigned" class="text-xs text-slate-500">{{ __('Chargement du fichier...') }}</div>
                                @error('countersigned') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                                <label class="flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" wire:model="notifyClientOnCountersign" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500/30">
                                    {{ __('Notifier le client par email (PDF joint)') }}
                                </label>
                                <button type="submit" wire:loading.attr="disabled" wire:target="uploadCountersigned,countersigned"
                                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
                                    {{ $submission->contract->countersigned_file_path ? __('Remplacer le contrat contresigné') : __('Ajouter le contrat contresigné') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            @endif

            @unless ($isContact)
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Pièces') }}</h2>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $submission->uploadedDocuments->count() }}</span>
                    </div>
                    <div class="p-5">
                        @if ($submission->uploadedDocuments->isEmpty())
                            <p class="text-sm text-slate-500">{{ __('Aucune pièce téléversée.') }}</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($submission->uploadedDocuments as $doc)
                                    <li wire:key="doc-{{ $doc->id }}" class="flex items-center gap-3 py-2.5 text-sm first:pt-0 last:pb-0">
                                        <svg class="h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <span class="font-medium text-slate-800">{{ $doc->type->label() }}</span>
                                        <span class="truncate text-slate-400">· {{ $doc->original_filename }}</span>
                                        <a href="{{ route('admin.submissions.document', ['submission' => $submission->id, 'document' => $doc->id]) }}"
                                            class="ml-auto shrink-0 font-medium text-brand-700 hover:underline">{{ __('Télécharger') }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            @endunless

            @if ($renewal)
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Renouvellement') }}</h2>
                    </div>
                    <div class="px-5 py-2">
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('État') }}</dt>
                                <dd>
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $renewal['severity'] === 'ok',
                                        'bg-amber-50 text-amber-700' => $renewal['severity'] === 'warn',
                                        'bg-red-50 text-red-700' => $renewal['severity'] === 'bad',
                                    ])>{{ $renewal['label'] }}</span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Payé jusqu\'à l\'année') }}</dt>
                                <dd class="font-medium text-slate-900">{{ $renewal['paidThroughYear'] ?? '-' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Prochain renouvellement') }}</dt>
                                <dd class="font-medium text-slate-900">{{ $renewal['nextRenewalDate']?->format('d/m/Y') ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            @endif

            {{-- Toujours affichee (meme sans paiement) pour que l'etat soit clair. Cartes plutot qu'un
                 tableau : lisible et sans debordement, s'adapte au mobile. --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Paiements') }}</h2>
                </div>
                <div class="space-y-3 p-5">
                    @forelse ($submission->payments as $payment)
                        @php($settled = in_array($payment->status, [\App\Enums\Payment\PaymentStatus::Succeeded, \App\Enums\Payment\PaymentStatus::Refunded], true))
                        <div wire:key="pay-{{ $payment->id }}" class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ number_format($payment->amount_cents / 100, 2, ',', ' ') }} {{ $payment->currency }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $payment->type->label() }}{{ $payment->service_year ? ' · '.$payment->service_year : '' }}</p>
                                </div>
                                <span @class([
                                    'inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-emerald-50 text-emerald-700' => $payment->status === \App\Enums\Payment\PaymentStatus::Succeeded,
                                    'bg-red-50 text-red-700' => $payment->status === \App\Enums\Payment\PaymentStatus::Failed,
                                    'bg-amber-50 text-amber-700' => in_array($payment->status, [\App\Enums\Payment\PaymentStatus::Pending, \App\Enums\Payment\PaymentStatus::Processing], true),
                                    'bg-slate-100 text-slate-600' => in_array($payment->status, [\App\Enums\Payment\PaymentStatus::Expired, \App\Enums\Payment\PaymentStatus::Refunded], true),
                                ])>{{ $payment->status->label() }}</span>
                            </div>
                            <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1.5 text-xs sm:grid-cols-2">
                                <div class="flex justify-between gap-3 sm:block">
                                    <dt class="text-slate-400">{{ __('Référence') }}</dt>
                                    <dd class="break-all text-right font-mono text-slate-600 sm:mt-0.5 sm:text-left">{{ $payment->provider }} · {{ $payment->provider_reference ?: '—' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 sm:block">
                                    <dt class="text-slate-400">{{ __('Payé le') }}</dt>
                                    <dd class="text-right text-slate-600 sm:mt-0.5 sm:text-left">{{ $payment->paid_at?->format('d/m/Y à H:i') ?: '—' }}</dd>
                                </div>
                            </dl>
                            @unless ($settled)
                                <div class="mt-3 border-t border-slate-100 pt-3">
                                    <button type="button" wire:click="recheckPayment({{ $payment->id }})"
                                        wire:loading.attr="disabled" wire:target="recheckPayment({{ $payment->id }})"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                                        <span wire:loading.remove wire:target="recheckPayment({{ $payment->id }})">{{ __('Vérifier chez le prestataire') }}</span>
                                        <span wire:loading wire:target="recheckPayment({{ $payment->id }})">{{ __('Vérification…') }}</span>
                                    </button>
                                </div>
                            @endunless
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Aucun paiement enregistré pour ce dossier.') }}</p>
                    @endforelse
                </div>
            </section>

            @if ($submission->appointment)
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Rendez-vous') }}</h2>
                    </div>
                    <div class="px-5 py-2">
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Programmé le') }}</dt>
                                <dd class="font-medium text-slate-900">{{ $submission->appointment->scheduled_at?->format('d/m/Y H:i') ?: '-' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ __('Statut') }}</dt>
                                <dd class="font-medium text-slate-900">{{ $submission->appointment->status->label() }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            @endif

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Notes internes') }}</h2>
                </div>
                <div class="p-5">
                    <form wire:submit="addNote" class="mb-5">
                        <textarea wire:model="noteBody" rows="3" placeholder="{{ __('Ajouter une note de suivi...') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30"></textarea>
                        @error('noteBody') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="addNote"
                            class="mt-2.5 rounded-lg bg-slate-800 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900 disabled:opacity-60">{{ __('Ajouter la note') }}</button>
                    </form>
                    @forelse ($submission->notes as $note)
                        <div class="border-t border-slate-100 py-3 first:border-t-0 first:pt-0" wire:key="note-{{ $note->id }}">
                            <div class="mb-1 text-xs text-slate-400">{{ $note->author?->name ?: __('Équipe') }} · {{ $note->created_at->format('d/m/Y à H:i') }}</div>
                            <div class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ $note->body }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Aucune note pour le moment.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-6 lg:sticky lg:top-8 lg:self-start">
            @unless ($isContact)
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Statut du dossier') }}</h2>
                    </div>
                    <div class="p-5">
                        <form wire:submit="updateStatus">
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="new-status">{{ __('Changer le statut') }}</label>
                            <select id="new-status" wire:model="newStatus"
                                class="mb-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                            <button type="submit" wire:loading.attr="disabled" wire:target="updateStatus"
                                class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
                                <span wire:loading.remove wire:target="updateStatus">{{ __('Enregistrer le statut') }}</span>
                                <span wire:loading wire:target="updateStatus">{{ __('Enregistrement') }}&hellip;</span>
                            </button>
                            @error('newStatus') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </form>
                    </div>
                </section>
            @endunless

            @if ($isStarter)
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Personne Responsable UE') }}</h2>
                    </div>
                    <div class="p-5">
                        <form wire:submit="issueResponsiblePerson">
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="rp-address">{{ __('Adresse délivrée') }}</label>
                            <textarea id="rp-address" wire:model="rpAddress" rows="3" placeholder="{{ __('Adresse officielle de représentation dans l\'UE...') }}"
                                class="mb-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30"></textarea>
                            <button type="submit" wire:loading.attr="disabled" wire:target="issueResponsiblePerson"
                                class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">{{ __('Émettre et terminer') }}</button>
                            @error('rpAddress') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </form>
                    </div>
                </section>
            @endif

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Actions') }}</h2>
                </div>
                <div class="space-y-2.5 p-5">
                    <button type="button" @click="emailOpen = true"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
                        {{ $isContact ? __('Répondre par email') : __('Envoyer un email') }}
                    </button>
                    @if ($isStarter && $submission->resume_token)
                        <button type="button" wire:click="resendLink" wire:target="resendLink" wire:loading.attr="disabled"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                            {{ $isPaid ? __('Renvoyer le lien du dossier') : __('Renvoyer le lien de reprise') }}
                        </button>
                    @endif
                </div>
            </section>

            <section class="rounded-xl border border-rose-200 bg-rose-50/50 shadow-sm">
                <div class="border-b border-rose-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-rose-700">{{ __('Zone sensible') }}</h2>
                </div>
                <div class="p-5">
                    <button type="button" wire:click="deleteDossier"
                        wire:confirm="{{ $isContact ? __('Supprimer définitivement cette prise de contact ? Action irréversible.') : __('Supprimer définitivement ce dossier et tous ses fichiers ? Action irréversible.') }}"
                        class="w-full rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700">{{ $isContact ? __('Supprimer la prise de contact') : __('Supprimer le dossier') }}</button>
                    <p class="mt-2 text-xs text-rose-600/80">{{ $isContact ? __('La prise de contact sera définitivement effacée (RGPD).') : __('Le dossier et tous ses fichiers seront définitivement effacés (RGPD).') }}</p>
                </div>
            </section>
        </aside>
    </div>

    <div x-show="emailOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4" @keydown.escape.window="emailOpen = false" style="display: none;">
        <div class="absolute inset-0 bg-slate-900/40" @click="emailOpen = false" x-show="emailOpen" x-transition.opacity></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" x-show="emailOpen" x-transition
            role="dialog" aria-modal="true" aria-labelledby="email-modal-title">
            <div class="mb-1 flex items-start justify-between gap-4">
                <h2 class="text-base font-semibold text-slate-900" id="email-modal-title">{{ $isContact ? __('Répondre au message') : __('Envoyer un email au client') }}</h2>
                <button type="button" class="text-slate-400 transition hover:text-slate-600" @click="emailOpen = false" aria-label="{{ __('Fermer') }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <p class="mb-5 text-sm text-slate-500">{{ __('À :') }} <span class="font-medium text-slate-700">{{ $submission->email }}</span></p>
            <form wire:submit="sendEmail" class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="email-subject">{{ __('Objet') }}</label>
                    <input id="email-subject" type="text" wire:model="emailSubject"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    @error('emailSubject') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="email-body">{{ __('Message') }}</label>
                    <textarea id="email-body" wire:model="emailBody" rows="7"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30"></textarea>
                    @error('emailBody') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2.5 pt-1">
                    <button type="button" @click="emailOpen = false"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">{{ __('Annuler') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="sendEmail"
                        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="sendEmail">{{ __('Envoyer') }}</span>
                        <span wire:loading wire:target="sendEmail">{{ __('Envoi') }}&hellip;</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
