<div x-data="{ emailOpen: false }" @email-sent.window="emailOpen = false">
    <nav class="mb-4 flex items-center gap-1.5 text-[13px] text-muted">
        <a href="{{ $isContact ? route('admin.contacts.index') : route('admin.submissions.index') }}" class="transition hover:text-primary">{{ $isContact ? __('Prises de contact') : __('Dossiers') }}</a>
        <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-muted" />
        <span class="font-medium text-secondary">{{ $isContact ? __('Prise de contact') : $submission->reference }}</span>
    </nav>

    @if ($isContact)
        <div class="mb-6">
            <x-ui.badge color="amber" dot>{{ __('Prise de contact') }}</x-ui.badge>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-primary">{{ $submission->first_name ?: __('Contact sans nom') }}</h1>
            <p class="mt-1 text-[13px] text-secondary">{{ __('Reçue le') }} {{ $submission->created_at->format('d/m/Y à H:i') }} · {{ __('via le formulaire de contact') }} · {{ __('Réf.') }} {{ $submission->reference }}</p>
        </div>
    @else
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-primary">{{ $submission->reference }}</h1>
                <p class="mt-1 text-[13px] text-secondary">{{ $submission->type->label() }} · {{ __('Créé le') }} {{ $submission->created_at->format('d/m/Y à H:i') }}</p>
            </div>
            <x-admin.dossier-state-badge :state="$dossierState" />
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-xl border border-base bg-surface">
                <div class="border-b border-subtle px-5 py-3.5">
                    <h2 class="text-[13px] font-semibold text-primary">{{ $isContact ? __('Coordonnées') : __('Client') }}</h2>
                </div>
                <div class="px-5 py-2">
                    <dl class="divide-y divide-subtle text-[13px]">
                        @php
                            $rows = $isContact
                                ? [
                                    [__('Nom'), $submission->first_name ?: '-', false],
                                    [__('Email'), $submission->email, true],
                                    [__('Site web'), $submission->website_url ?: '-', false],
                                    [__('Langue'), strtoupper((string) $submission->locale), false],
                                ]
                                : [
                                    [__('Entreprise'), $submission->company_name ?: '-', false],
                                    [__('Contact'), trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: '-', false],
                                    [__('Email'), $submission->email, true],
                                    [__('Téléphone'), $submission->phone ?: '-', false],
                                    [__('Site web'), $submission->website_url ?: '-', false],
                                    [__('N° immatriculation'), $submission->company_registration_number ?: '-', false],
                                    [__('Langue du dossier'), strtoupper((string) $submission->locale), false],
                                ];
                        @endphp
                        @foreach ($rows as [$label, $value, $isEmail])
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="text-muted">{{ $label }}</dt>
                                @if ($isEmail)
                                    <dd class="text-right font-medium"><a href="mailto:{{ $value }}" class="text-gray-900 hover:underline dark:text-gray-100">{{ $value }}</a></dd>
                                @else
                                    <dd class="text-right font-medium text-primary">{{ $value }}</dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            @if ($submission->message)
                <section class="rounded-xl border border-base bg-surface">
                    <div class="border-b border-subtle px-5 py-3.5">
                        <h2 class="text-[13px] font-semibold text-primary">{{ __('Message') }}</h2>
                    </div>
                    <div class="p-5">
                        <p class="whitespace-pre-wrap text-[13px] leading-relaxed text-secondary">{{ $submission->message }}</p>
                    </div>
                </section>
            @endif

            @if ($submission->contract)
                <section class="rounded-xl border border-base bg-surface">
                    <div class="border-b border-subtle px-5 py-3.5">
                        <h2 class="text-[13px] font-semibold text-primary">{{ __('Mandat / signature') }}</h2>
                    </div>
                    <div class="px-5 py-2">
                        <dl class="divide-y divide-subtle text-[13px]">
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-muted">{{ __('Statut de signature') }}</dt>
                                <dd class="font-medium text-primary">{{ $submission->contract->signature_status->label() }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-muted">{{ __('Prestataire') }}</dt>
                                <dd class="font-medium text-primary">{{ $submission->contract->signature_provider ?: '-' }}</dd>
                            </div>
                        </dl>
                        @if ($submission->contract->signed_file_path)
                            <x-admin.document-item class="mt-3"
                                :title="__('Mandat signé')"
                                :subtitle="$submission->contract->signed_at ? __('Signé le').' '.$submission->contract->signed_at->format('d/m/Y') : __('Document PDF')"
                                :download-url="route('admin.submissions.mandate', ['submission' => $submission->id])" />
                        @endif

                        {{-- Contrat contresigne par Festilaw (contre-signature manuelle, hors SignWell) --}}
                        @php
                            $hasCountersigned = (bool) $submission->contract->countersigned_file_path;
                        @endphp
                        <div class="mt-4 border-t border-subtle pt-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">{{ __('Contrat contresigné') }}</p>

                            @if ($hasCountersigned)
                                <x-admin.document-item class="mt-2"
                                    :title="__('Contrat contresigné')"
                                    :subtitle="__('Déposé le').' '.$submission->contract->countersigned_at?->format('d/m/Y à H:i')"
                                    :download-url="route('admin.submissions.countersigned', ['submission' => $submission->id])" />
                            @else
                                <p class="mt-1 text-[12px] text-muted">{{ __('Aucun contrat contresigné déposé pour le moment.') }}</p>
                            @endif

                            <form wire:submit="uploadCountersigned" class="mt-4 space-y-3">
                                <p class="text-[12px] font-medium text-secondary">
                                    {{ $hasCountersigned ? __('Remplacer le document par un nouveau PDF') : __('Déposer le contrat contresigné') }}
                                </p>
                                {{-- Zone glisser-deposer + selecteur, compatible Livewire (wire:model sur l'input cache). --}}
                                <div x-data="{ dragging: false, name: null }"
                                    @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                                    @drop.prevent="dragging = false; $refs.cs.files = $event.dataTransfer.files; $refs.cs.dispatchEvent(new Event('change', { bubbles: true })); name = $event.dataTransfer.files[0]?.name"
                                    :class="dragging ? 'border-gray-400 bg-elevated' : 'border-base hover:border-gray-300'"
                                    class="rounded-lg border-2 border-dashed px-6 py-8 text-center transition-colors">
                                    <input type="file" x-ref="cs" wire:model="countersigned" accept="application/pdf" class="sr-only" @change="name = $refs.cs.files[0]?.name">
                                    <template x-if="!name">
                                        <div>
                                            <x-ui.icon name="document-arrow-up" class="mx-auto h-9 w-9 text-gray-300 dark:text-gray-600" stroke-width="1" />
                                            <div class="mt-3 text-[13px]">
                                                <button type="button" @click="$refs.cs.click()" class="font-medium text-primary underline-offset-2 hover:underline">{{ __('Choisir un PDF') }}</button>
                                                <span class="text-muted"> {{ __('ou glisser-déposer') }}</span>
                                            </div>
                                            <p class="mt-1 text-[11px] text-muted">{{ $hasCountersigned ? __('Le document actuel sera remplacé · PDF, 10 Mo max') : __('PDF jusqu\'à 10 Mo') }}</p>
                                        </div>
                                    </template>
                                    <template x-if="name">
                                        <div class="flex items-center justify-center gap-x-2 text-[13px]">
                                            <x-ui.icon name="document-text" class="h-5 w-5 text-emerald-500" />
                                            <span class="font-medium text-primary" x-text="name"></span>
                                            <button type="button" @click="name = null; $refs.cs.value = null" class="text-muted hover:text-secondary" aria-label="{{ __('Retirer') }}"><x-ui.icon name="x-mark" class="h-4 w-4" /></button>
                                        </div>
                                    </template>
                                </div>
                                <div wire:loading wire:target="countersigned" class="text-[12px] text-muted">{{ __('Chargement du fichier...') }}</div>
                                @error('countersigned') <p class="text-[12px] text-red-500">{{ $message }}</p> @enderror
                                <x-ui.checkbox id="notify-countersign" wire:model="notifyClientOnCountersign" label="{{ __('Notifier le client par email (PDF joint)') }}" />
                                <x-ui.button type="submit" :loading="true" target="uploadCountersigned,countersigned">
                                    {{ $hasCountersigned ? __('Remplacer le contrat contresigné') : __('Ajouter le contrat contresigné') }}
                                </x-ui.button>
                            </form>
                        </div>
                    </div>
                </section>
            @endif

            @unless ($isContact)
                <section class="rounded-xl border border-base bg-surface">
                    <div class="flex items-center justify-between border-b border-subtle px-5 py-3.5">
                        <h2 class="text-[13px] font-semibold text-primary">{{ __('Pièces') }}</h2>
                        <x-ui.badge color="gray">{{ $submission->uploadedDocuments->count() }}</x-ui.badge>
                    </div>
                    <div class="p-5">
                        @if ($submission->uploadedDocuments->isEmpty())
                            <p class="text-[13px] text-secondary">{{ __('Aucune pièce téléversée.') }}</p>
                        @else
                            <div class="space-y-2">
                                @foreach ($submission->uploadedDocuments as $doc)
                                    <x-admin.document-item wire:key="doc-{{ $doc->id }}"
                                        :title="$doc->type->label()"
                                        :subtitle="$doc->original_filename"
                                        :download-url="route('admin.submissions.document', ['submission' => $submission->id, 'document' => $doc->id])" />
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endunless

            @if ($renewal)
                <section class="rounded-xl border border-base bg-surface">
                    <div class="border-b border-subtle px-5 py-3.5">
                        <h2 class="text-[13px] font-semibold text-primary">{{ __('Renouvellement') }}</h2>
                    </div>
                    <div class="px-5 py-2">
                        <dl class="divide-y divide-subtle text-[13px]">
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-muted">{{ __('État') }}</dt>
                                <dd><x-ui.badge :color="['ok' => 'emerald', 'warn' => 'amber', 'bad' => 'red'][$renewal['severity']] ?? 'gray'" dot>{{ $renewal['label'] }}</x-ui.badge></dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-muted">{{ __('Payé jusqu\'à l\'année') }}</dt>
                                <dd class="font-medium text-primary">{{ $renewal['paidThroughYear'] ?? '-' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-muted">{{ __('Prochain renouvellement') }}</dt>
                                <dd class="font-medium text-primary">{{ $renewal['nextRenewalDate']?->format('d/m/Y') ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            @endif

            {{-- Toujours affichee (meme sans paiement) pour que l'etat soit clair. --}}
            <section class="rounded-xl border border-base bg-surface">
                <div class="border-b border-subtle px-5 py-3.5">
                    <h2 class="text-[13px] font-semibold text-primary">{{ __('Paiements') }}</h2>
                </div>
                <div class="space-y-3 p-5">
                    @forelse ($submission->payments as $payment)
                        @php
                            $settled = in_array($payment->status, [\App\Enums\Payment\PaymentStatus::Succeeded, \App\Enums\Payment\PaymentStatus::Refunded], true);
                            $payColor = match ($payment->status) {
                                \App\Enums\Payment\PaymentStatus::Succeeded => 'emerald',
                                \App\Enums\Payment\PaymentStatus::Failed => 'red',
                                \App\Enums\Payment\PaymentStatus::Pending, \App\Enums\Payment\PaymentStatus::Processing => 'amber',
                                default => 'gray',
                            };
                        @endphp
                        <div wire:key="pay-{{ $payment->id }}" class="rounded-lg border border-base p-4">
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                                <div>
                                    <p class="font-semibold text-primary">{{ number_format($payment->amount_cents / 100, 2, ',', ' ') }} {{ $payment->currency }}</p>
                                    <p class="mt-0.5 text-[12px] text-muted">{{ $payment->type->label() }}{{ $payment->service_year ? ' · '.$payment->service_year : '' }}</p>
                                </div>
                                <x-ui.badge :color="$payColor" dot>{{ $payment->status->label() }}</x-ui.badge>
                            </div>
                            <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1.5 text-[12px] sm:grid-cols-2">
                                <div class="flex justify-between gap-3 sm:block">
                                    <dt class="text-muted">{{ __('Référence') }}</dt>
                                    <dd class="break-all text-right font-mono text-secondary sm:mt-0.5 sm:text-left">{{ $payment->provider }} · {{ $payment->provider_reference ?: '—' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 sm:block">
                                    <dt class="text-muted">{{ __('Payé le') }}</dt>
                                    <dd class="text-right text-secondary sm:mt-0.5 sm:text-left">{{ $payment->paid_at?->format('d/m/Y à H:i') ?: '—' }}</dd>
                                </div>
                            </dl>
                            @unless ($settled)
                                <div class="mt-3 border-t border-subtle pt-3">
                                    <x-ui.button variant="secondary" size="compact" wire:click="recheckPayment({{ $payment->id }})" :loading="true" target="recheckPayment({{ $payment->id }})">
                                        <x-ui.icon name="arrow-path" class="h-3.5 w-3.5" />
                                        {{ __('Vérifier sur :provider', ['provider' => $payment->providerLabel()]) }}
                                    </x-ui.button>
                                </div>
                            @endunless
                        </div>
                    @empty
                        <p class="text-[13px] text-secondary">{{ __('Aucun paiement enregistré pour ce dossier.') }}</p>
                    @endforelse
                </div>
            </section>

            @if ($isScale)
                <section class="rounded-xl border border-base bg-surface">
                    <div class="border-b border-subtle px-5 py-3.5">
                        <h2 class="text-[13px] font-semibold text-primary">{{ __('Audit Scale') }}</h2>
                    </div>
                    <div class="px-5 py-4">
                        @if ($scaleAuditPaid)
                            <x-ui.badge color="emerald" ring>{{ __('Audit 75 € payé · à déduire du devis final') }}</x-ui.badge>
                        @else
                            <x-ui.badge color="gray" ring>{{ __('Audit non payé') }}</x-ui.badge>
                        @endif
                    </div>
                </section>
            @endif

            @if ($submission->appointment)
                <section class="rounded-xl border border-base bg-surface">
                    <div class="border-b border-subtle px-5 py-3.5">
                        <h2 class="text-[13px] font-semibold text-primary">{{ __('Rendez-vous') }}</h2>
                    </div>
                    <div class="p-5">
                        <form wire:submit="updateAppointment" class="space-y-4">
                            <x-ui.form-group label="{{ __('Créneau confirmé') }}" for="appt-scheduled" :error="$errors->first('apptScheduledAt')">
                                <x-ui.input type="datetime-local" id="appt-scheduled" wire:model="apptScheduledAt" :error="$errors->has('apptScheduledAt')" />
                            </x-ui.form-group>
                            <x-ui.form-group label="{{ __('Statut') }}" for="appt-status" :error="$errors->first('apptStatus')">
                                <x-ui.select id="appt-status" wire:model="apptStatus" :options="collect($appointmentStatuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :error="$errors->has('apptStatus')" />
                            </x-ui.form-group>
                            <x-ui.button type="submit" :loading="true" target="updateAppointment" class="w-full justify-center">{{ __('Enregistrer le rendez-vous') }}</x-ui.button>
                        </form>
                    </div>
                </section>
            @endif

            <section class="rounded-xl border border-base bg-surface">
                <div class="border-b border-subtle px-5 py-3.5">
                    <h2 class="text-[13px] font-semibold text-primary">{{ __('Notes internes') }}</h2>
                </div>
                <div class="p-5">
                    <form wire:submit="addNote" class="mb-5">
                        <x-ui.form-group :error="$errors->first('noteBody')">
                            <x-ui.textarea wire:model="noteBody" rows="3" placeholder="{{ __('Ajouter une note de suivi...') }}" :error="$errors->has('noteBody')" />
                        </x-ui.form-group>
                        <x-ui.button type="submit" :loading="true" target="addNote" size="compact" class="mt-2.5">{{ __('Ajouter la note') }}</x-ui.button>
                    </form>
                    @forelse ($submission->notes as $note)
                        <div class="border-t border-subtle py-3 first:border-t-0 first:pt-0" wire:key="note-{{ $note->id }}">
                            <div class="mb-1 text-[12px] text-muted">{{ $note->author?->name ?: __('Équipe') }} · {{ $note->created_at->format('d/m/Y à H:i') }}</div>
                            <div class="whitespace-pre-wrap text-[13px] leading-relaxed text-secondary">{{ $note->body }}</div>
                        </div>
                    @empty
                        <p class="text-[13px] text-secondary">{{ __('Aucune note pour le moment.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-6 lg:sticky lg:top-8 lg:self-start">
            @unless ($isContact)
                <section class="rounded-xl border border-base bg-surface">
                    <div class="border-b border-subtle px-5 py-3.5">
                        <h2 class="text-[13px] font-semibold text-primary">{{ __('Statut du dossier') }}</h2>
                    </div>
                    <div class="p-5">
                        <form wire:submit="updateStatus">
                            <x-ui.form-group label="{{ __('Changer le statut') }}" for="new-status" class="mb-3" :error="$errors->first('newStatus')">
                                <x-ui.select id="new-status" wire:model="newStatus" :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :error="$errors->has('newStatus')" />
                            </x-ui.form-group>
                            <x-ui.button type="submit" :loading="true" target="updateStatus" class="w-full justify-center">{{ __('Enregistrer le statut') }}</x-ui.button>
                        </form>
                    </div>
                </section>
            @endunless

            @if ($isOnlineJourney)
                <section class="rounded-xl border border-base bg-surface">
                    <div class="border-b border-subtle px-5 py-3.5">
                        <h2 class="text-[13px] font-semibold text-primary">{{ __('Personne Responsable UE') }}</h2>
                    </div>
                    <div class="p-5">
                        <form wire:submit="issueResponsiblePerson">
                            <x-ui.form-group label="{{ __('Adresse délivrée') }}" for="rp-address" class="mb-3" :error="$errors->first('rpAddress')">
                                <x-ui.textarea id="rp-address" wire:model="rpAddress" rows="3" placeholder="{{ __('Adresse officielle de représentation dans l\'UE...') }}" :error="$errors->has('rpAddress')" />
                            </x-ui.form-group>
                            <x-ui.button type="submit" :loading="true" target="issueResponsiblePerson" class="w-full justify-center">{{ __('Émettre et terminer') }}</x-ui.button>
                        </form>
                    </div>
                </section>
            @endif

            <section class="rounded-xl border border-base bg-surface">
                <div class="border-b border-subtle px-5 py-3.5">
                    <h2 class="text-[13px] font-semibold text-primary">{{ __('Actions') }}</h2>
                </div>
                <div class="space-y-2.5 p-5">
                    <x-ui.button variant="secondary" @click="emailOpen = true" class="w-full justify-center">
                        <x-ui.icon name="envelope" class="h-4 w-4" />
                        {{ $isContact ? __('Répondre par email') : __('Envoyer un email') }}
                    </x-ui.button>
                    @if ($isOnlineJourney && $submission->resume_token)
                        <x-ui.button variant="secondary" wire:click="resendLink" :loading="true" target="resendLink" class="w-full justify-center">
                            <x-ui.icon name="arrow-path" class="h-4 w-4" />
                            {{ $isPaid ? __('Renvoyer le lien du dossier') : __('Renvoyer le lien de reprise') }}
                        </x-ui.button>
                    @endif
                </div>
            </section>

            <section class="rounded-xl border border-red-100 bg-red-50/40 dark:border-red-500/20 dark:bg-red-500/10">
                <div class="border-b border-red-100 px-5 py-3.5 dark:border-red-500/20">
                    <h2 class="text-[13px] font-semibold text-red-700 dark:text-red-400">{{ __('Zone sensible') }}</h2>
                </div>
                <div class="p-5">
                    <x-ui.button variant="danger" wire:click="deleteDossier"
                        wire:confirm="{{ $isContact ? __('Supprimer définitivement cette prise de contact ? Action irréversible.') : __('Supprimer définitivement ce dossier et tous ses fichiers ? Action irréversible.') }}"
                        class="w-full justify-center">{{ $isContact ? __('Supprimer la prise de contact') : __('Supprimer le dossier') }}</x-ui.button>
                    <p class="mt-2 text-[12px] text-red-600/80 dark:text-red-400/80">{{ $isContact ? __('La prise de contact sera définitivement effacée (RGPD).') : __('Le dossier et tous ses fichiers seront définitivement effacés (RGPD).') }}</p>
                </div>
            </section>
        </aside>
    </div>

    <div x-show="emailOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4" @keydown.escape.window="emailOpen = false" style="display: none;">
        <div class="absolute inset-0 bg-gray-900/40" @click="emailOpen = false" x-show="emailOpen" x-transition.opacity></div>
        <div class="relative w-full max-w-lg rounded-xl border border-base bg-surface p-6 shadow-2xl" x-show="emailOpen" x-transition
            role="dialog" aria-modal="true" aria-labelledby="email-modal-title">
            <div class="mb-1 flex items-start justify-between gap-4">
                <h2 class="text-base font-semibold text-primary" id="email-modal-title">{{ $isContact ? __('Répondre au message') : __('Envoyer un email au client') }}</h2>
                <button type="button" class="text-muted transition hover:text-secondary" @click="emailOpen = false" aria-label="{{ __('Fermer') }}">
                    <x-ui.icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>
            <p class="mb-5 text-[13px] text-secondary">{{ __('À :') }} <span class="font-medium text-primary">{{ $submission->email }}</span></p>
            <form wire:submit="sendEmail" class="space-y-4">
                <x-ui.form-group label="{{ __('Objet') }}" for="email-subject" :error="$errors->first('emailSubject')">
                    <x-ui.input id="email-subject" type="text" wire:model="emailSubject" :error="$errors->has('emailSubject')" />
                </x-ui.form-group>
                <x-ui.form-group label="{{ __('Message') }}" for="email-body" :error="$errors->first('emailBody')">
                    <x-ui.textarea id="email-body" wire:model="emailBody" rows="7" :error="$errors->has('emailBody')" />
                </x-ui.form-group>
                <div class="flex justify-end gap-2.5 pt-1">
                    <x-ui.button variant="ghost" @click="emailOpen = false">{{ __('Annuler') }}</x-ui.button>
                    <x-ui.button type="submit" :loading="true" target="sendEmail">{{ __('Envoyer') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
