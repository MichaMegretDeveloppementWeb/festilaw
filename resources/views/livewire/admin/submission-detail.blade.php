<div x-data @email-sent.window="$dispatch('ui-close-modal', 'send-email')">
    <x-ui::breadcrumb class="mb-4">
        <x-ui::breadcrumb.item :href="$isContact ? route('admin.contacts.index') : route('admin.submissions.index')">{{ $isContact ? __('Prises de contact') : __('Dossiers') }}</x-ui::breadcrumb.item>
        <x-ui::breadcrumb.item>{{ $isContact ? __('Prise de contact') : $submission->reference }}</x-ui::breadcrumb.item>
    </x-ui::breadcrumb>

    @if ($isContact)
        <div class="mb-6">
            <x-ui::badge color="amber" dot>{{ __('Prise de contact') }}</x-ui::badge>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-primary">{{ $submission->first_name ?: __('Contact sans nom') }}</h1>
            <p class="mt-1 text-[13px] text-secondary">{{ __('Reçue le') }} {{ $submission->created_at->format('d/m/Y à H:i') }} · {{ __('via le formulaire de contact') }} · {{ __('Réf.') }} {{ $submission->reference }}</p>
            {{-- Politique de confidentialite : suppression 12 mois apres le dernier echange (ApplyPrivacyRetention). --}}
            <p class="mt-1 text-[12px] text-muted">{{ __('Suppression automatique le :date, sans nouvel échange d\'ici là. Une note ou un e-mail envoyé d\'ici repousse ce délai ; les réponses envoyées depuis votre boîte mail ne comptent pas.', ['date' => $contactDeletionDate->format('d/m/Y')]) }}</p>
        </div>
    @else
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-primary">{{ $submission->reference }}</h1>
                <p class="mt-1 text-[13px] text-secondary">{{ $submission->type->label() }} · {{ __('Créé le') }} {{ $submission->created_at->format('d/m/Y à H:i') }}</p>
            </div>
            <x-admin.dossier-state-badge :state="$dossierState" />
        </div>

        {{-- Changement de pack avant paiement : l'ancien dossier (annule) et son remplacant se pointent l'un l'autre. --}}
        @if ($submission->replacedBy)
            <x-ui::alert type="warning" class="mb-6">
                {{ __('Remplacé par') }}
                <a href="{{ route('admin.submissions.show', ['submission' => $submission->replacedBy->id]) }}" class="font-semibold underline">{{ $submission->replacedBy->reference }}</a>
                · {{ __('changement de pack vers :pack le :date par le client.', ['pack' => $submission->replacedBy->type->label(), 'date' => $submission->replacedBy->created_at->format('d/m/Y à H:i')]) }}
            </x-ui::alert>
        @endif
        @if ($submission->packChanges->contains('status', \App\Enums\Submission\PackChangeStatus::Requested))
            {{-- Retour au Creator demande par le client (SC12) : Festilaw a le dernier mot. --}}
            <x-ui::alert type="warning" class="mb-6">
                {{ __('Demande de passage au Creator à valider : vérifiez le justificatif de chiffre d\'affaires (Pièces) et l\'attestation du client (moins de 35 000 € de CA, 9 produits maximum), puis validez ou refusez dans la carte « Changement de pack ».') }}
            </x-ui::alert>
        @endif
        @if ($submission->replaces)
            <x-ui::alert type="info" class="mb-6">
                {{ __('Remplace') }}
                <a href="{{ route('admin.submissions.show', ['submission' => $submission->replaces->id]) }}" class="font-semibold underline">{{ $submission->replaces->reference }}</a>
                · {{ __(':pack, changé par le client avant paiement (informations et pièces reprises).', ['pack' => $submission->replaces->type->label()]) }}
            </x-ui::alert>
        @endif
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.panel :title="$isContact ? __('Coordonnées') : __('Client')">
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
                <x-ui::description-list variant="inline">
                    @foreach ($rows as [$label, $value, $isEmail])
                        <x-ui::description-list.item variant="inline" :label="$label">
                            @if ($isEmail)
                                <a href="mailto:{{ $value }}" class="break-all hover:underline">{{ $value }}</a>
                            @else
                                {{ $value }}
                            @endif
                        </x-ui::description-list.item>
                    @endforeach
                </x-ui::description-list>
            </x-admin.panel>

            @if ($submission->message)
                <x-admin.panel :title="__('Message')">
                    <div class="p-5">
                        <p class="whitespace-pre-wrap text-[13px] leading-relaxed text-secondary">{{ $submission->message }}</p>
                    </div>
                </x-admin.panel>
            @endif

            @if ($submission->contract)
                <x-admin.panel :title="__('Mandat / signature')">
                    <x-ui::description-list variant="inline">
                        <x-ui::description-list.item variant="inline" :label="__('Pack du mandat')">{{ $submission->contract->packType()->label() }}</x-ui::description-list.item>
                        <x-ui::description-list.item variant="inline" :label="__('Statut de signature')">{{ $submission->contract->signature_status->label() }}</x-ui::description-list.item>
                        <x-ui::description-list.item variant="inline" :label="__('Prestataire')">{{ $submission->contract->signature_provider ?: '-' }}</x-ui::description-list.item>
                    </x-ui::description-list>
                    <div class="px-5 pb-2">
                        @if ($submission->contract->signed_file_path)
                            <x-admin.document-item class="mt-3"
                                :title="__('Mandat signé')"
                                :subtitle="$submission->contract->signed_at ? __('Signé le').' '.$submission->contract->signed_at->format('d/m/Y') : __('Document PDF')"
                                :download-url="route('admin.submissions.mandate', ['submission' => $submission->id])" />
                        @elseif ($submission->contract->signature_status === \App\Enums\Contract\SignatureStatus::Signed)
                            {{-- Mandat signe mais PDF local absent (echec transitoire du prestataire) : recuperation manuelle immediate. --}}
                            <div class="mt-3 flex flex-col gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/40 dark:bg-amber-950/30">
                                <div class="flex items-start gap-2.5">
                                    <x-ui::icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                                    <div>
                                        <p class="text-[13px] font-medium text-primary">{{ __('Mandat signé, PDF manquant') }}</p>
                                        <p class="text-[12px] text-muted">{{ __('La signature a bien eu lieu mais le PDF n\'a pas pu être téléchargé. Récupérez-le maintenant.') }}</p>
                                    </div>
                                </div>
                                <x-ui::button variant="secondary" size="compact" wire:click="recoverSignedMandate" :loading="true" target="recoverSignedMandate" class="shrink-0">
                                    <x-ui::icon name="arrow-down-tray" class="h-3.5 w-3.5" />
                                    {{ __('Récupérer le PDF signé') }}
                                </x-ui::button>
                            </div>
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
                                <x-ui::upload model="countersigned" name="countersigned" accept="application/pdf" max-size="10 Mo"
                                    :hint="$hasCountersigned ? __('Le document actuel sera remplacé · PDF') : 'PDF'" />
                                <div wire:loading wire:target="countersigned" class="text-[12px] text-muted">{{ __('Chargement du fichier...') }}</div>
                                @error('countersigned') <p class="text-[12px] text-red-500">{{ $message }}</p> @enderror
                                <x-ui::checkbox id="notify-countersign" wire:model="notifyClientOnCountersign" :label="__('Notifier le client par email (PDF joint)')" />
                                <x-ui::button type="submit" :loading="true" target="uploadCountersigned,countersigned">
                                    {{ $hasCountersigned ? __('Remplacer le contrat contresigné') : __('Ajouter le contrat contresigné') }}
                                </x-ui::button>
                            </form>
                        </div>

                        {{-- Mandats remplaces par un changement de pack apres paiement (SC12) : conserves comme trace. --}}
                        @php
                            $previousMandates = $submission->contracts->where('role', \App\Enums\Contract\ContractRole::Superseded)->whereNotNull('signed_file_path');
                        @endphp
                        @if ($previousMandates->isNotEmpty())
                            <div class="mt-4 border-t border-subtle pt-4 pb-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">{{ __('Mandats précédents') }}</p>
                                @foreach ($previousMandates as $previous)
                                    <x-admin.document-item class="mt-2"
                                        :title="__('Mandat :pack', ['pack' => $previous->packType()->label()])"
                                        :subtitle="__('Signé le').' '.$previous->signed_at?->format('d/m/Y').($previous->superseded_at ? ' · '.__('remplacé le').' '.$previous->superseded_at->format('d/m/Y') : '')"
                                        :download-url="route('admin.submissions.contract-mandate', ['submission' => $submission->id, 'contract' => $previous->id])" />
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-admin.panel>
            @endif

            @unless ($isContact)
                <x-admin.panel :title="__('Pièces')">
                    <x-slot:actions>
                        <x-ui::badge color="gray">{{ $submission->uploadedDocuments->count() }}</x-ui::badge>
                    </x-slot:actions>
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
                </x-admin.panel>
            @endunless

            @if ($renewal)
                <x-admin.panel :title="__('Renouvellement')">
                    <x-ui::description-list variant="inline">
                        <x-ui::description-list.item variant="inline" :label="__('État')">
                            <x-ui::badge :color="['ok' => 'emerald', 'warn' => 'amber', 'bad' => 'red'][$renewal['severity']] ?? 'gray'" :dot="true">{{ $renewal['label'] }}</x-ui::badge>
                        </x-ui::description-list.item>
                        <x-ui::description-list.item variant="inline" :label="__('Payé jusqu\'à l\'année')">{{ $renewal['paidThroughYear'] ?? '-' }}</x-ui::description-list.item>
                        <x-ui::description-list.item variant="inline" :label="__('Prochain renouvellement')">{{ $renewal['nextRenewalDate']?->format('d/m/Y') ?? '-' }}</x-ui::description-list.item>
                    </x-ui::description-list>
                </x-admin.panel>
            @endif

            {{-- Paiements : jamais pour une prise de contact ; pour un dossier, affichee meme sans paiement
                 (pour que l'etat soit clair). --}}
            @unless ($isContact)
            <x-admin.panel :title="__('Paiements')">
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
                        <div wire:key="pay-{{ $payment->id }}" class="rounded-lg border border-default p-4">
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                                <div>
                                    <p class="font-semibold text-primary">{{ number_format($payment->amount_cents / 100, 2, ',', ' ') }} {{ $payment->currency }}</p>
                                    <p class="mt-0.5 text-[12px] text-muted">{{ $payment->type->label() }}{{ $payment->service_year ? ' · '.$payment->service_year : '' }}</p>
                                </div>
                                <x-ui::badge :color="$payColor" dot>{{ $payment->status->label() }}</x-ui::badge>
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
                                    <x-ui::button variant="secondary" size="compact" wire:click="recheckPayment({{ $payment->id }})" :loading="true" target="recheckPayment({{ $payment->id }})">
                                        <x-ui::icon name="arrow-path" class="h-3.5 w-3.5" />
                                        {{ __('Vérifier sur :provider', ['provider' => $payment->providerLabel()]) }}
                                    </x-ui::button>
                                </div>
                            @endunless
                        </div>
                    @empty
                        <p class="text-[13px] text-secondary">{{ __('Aucun paiement enregistré pour ce dossier.') }}</p>
                    @endforelse
                </div>
            </x-admin.panel>
            @endunless

            @if ($isScale)
                <x-admin.panel :title="__('Audit Scale')">
                    <div class="px-5 py-4">
                        @if ($scaleAuditPaid)
                            <x-ui::badge color="emerald" ring>{{ __('Audit 75 € payé · à déduire du devis final') }}</x-ui::badge>
                        @elseif ($submission->appointment)
                            {{-- La consultation se reserve avant le paiement : un creneau peut etre pris sans audit regle. --}}
                            <x-ui::badge color="amber" ring>{{ __('Réservé · audit non payé') }}</x-ui::badge>
                            <p class="mt-2 text-[12px] text-secondary">{{ __('Le client a réservé un créneau mais n\'a pas encore réglé l\'audit. S\'il ne le règle pas, annulez le créneau dans Google Agenda.') }}</p>
                        @else
                            <x-ui::badge color="gray" ring>{{ __('Audit non payé') }}</x-ui::badge>
                        @endif
                    </div>
                </x-admin.panel>
            @endif

            @if ($submission->appointment)
                <x-admin.panel :title="__('Rendez-vous')">
                    <div class="p-5">
                        <form wire:submit="updateAppointment" class="space-y-4">
                            <x-ui::form-group :label="__('Créneau confirmé')" for="appt-scheduled" :error="$errors->first('apptScheduledAt')">
                                <x-ui::input type="datetime-local" id="appt-scheduled" wire:model="apptScheduledAt" :error="$errors->has('apptScheduledAt')" />
                            </x-ui::form-group>
                            <x-ui::form-group :label="__('Statut')" for="appt-status" :error="$errors->first('apptStatus')">
                                <x-ui::select id="appt-status" wire:model="apptStatus" :options="collect($appointmentStatuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :error="$errors->has('apptStatus')" />
                            </x-ui::form-group>
                            <x-ui::button type="submit" :loading="true" target="updateAppointment" class="w-full">{{ __('Enregistrer le rendez-vous') }}</x-ui::button>
                        </form>
                    </div>
                </x-admin.panel>
            @endif

            <x-admin.panel :title="__('Notes internes')">
                <div class="p-5">
                    <form wire:submit="addNote" class="mb-5">
                        <x-ui::form-group :error="$errors->first('noteBody')">
                            <x-ui::textarea wire:model="noteBody" rows="3" :placeholder="__('Ajouter une note de suivi...')" :error="$errors->has('noteBody')" />
                        </x-ui::form-group>
                        <x-ui::button type="submit" :loading="true" target="addNote" size="compact" class="mt-2.5">{{ __('Ajouter la note') }}</x-ui::button>
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
            </x-admin.panel>
        </div>

        <aside class="space-y-6 lg:sticky lg:top-8 lg:self-start">
            @unless ($isContact)
                <x-admin.panel :title="__('Statut du dossier')">
                    <div class="p-5">
                        <form wire:submit="updateStatus">
                            <x-ui::form-group :label="__('Changer le statut')" for="new-status" class="mb-3" :error="$errors->first('newStatus')">
                                <x-ui::select id="new-status" wire:model="newStatus" :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :error="$errors->has('newStatus')" />
                            </x-ui::form-group>
                            <x-ui::button type="submit" :loading="true" target="updateStatus" class="w-full">{{ __('Enregistrer le statut') }}</x-ui::button>
                        </form>
                    </div>
                </x-admin.panel>
            @endunless

            @if ($isOnlineJourney)
                <x-admin.panel :title="__('Personne Responsable UE')">
                    <div class="p-5">
                        <form wire:submit="issueResponsiblePerson">
                            <x-ui::form-group :label="__('Adresse délivrée')" for="rp-address" class="mb-3" :error="$errors->first('rpAddress')">
                                <x-ui::textarea id="rp-address" wire:model="rpAddress" rows="3" :placeholder="__('Adresse officielle de représentation dans l\'UE...')" :error="$errors->has('rpAddress')" />
                            </x-ui::form-group>
                            <x-ui::button type="submit" :loading="true" target="issueResponsiblePerson" class="w-full">{{ __('Émettre et terminer') }}</x-ui::button>
                        </form>
                    </div>
                </x-admin.panel>
            @endif

            {{-- Changements de pack apres paiement (SC12) : historique, et le "dernier mot" de Festilaw. --}}
            @if ($submission->packChanges->isNotEmpty())
                <x-admin.panel :title="__('Changement de pack')">
                    {{-- Les changements qui attendent une confirmation : leurs fenetres sont rendues en bas de page. --}}
                    @php $downgradeRequests = $revertibleUpgrades = []; @endphp
                    <div class="divide-y divide-subtle px-5">
                        @foreach ($submission->packChanges as $change)
                            @php
                                $changeColor = match ($change->status) {
                                    \App\Enums\Submission\PackChangeStatus::Completed, \App\Enums\Submission\PackChangeStatus::Applied, \App\Enums\Submission\PackChangeStatus::Approved => 'emerald',
                                    \App\Enums\Submission\PackChangeStatus::Open, \App\Enums\Submission\PackChangeStatus::Requested => 'amber',
                                    \App\Enums\Submission\PackChangeStatus::Rejected, \App\Enums\Submission\PackChangeStatus::Reverted => 'red',
                                    default => 'gray',
                                };
                                $revertible = $change->isUpgrade()
                                    && $change->status === \App\Enums\Submission\PackChangeStatus::Completed
                                    && $change->payment?->status === \App\Enums\Payment\PaymentStatus::Succeeded;
                            @endphp
                            <div class="py-3.5 text-[13px]">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-primary">{{ $change->from_pack->label() }} → {{ $change->to_pack->label() }}</span>
                                    <x-ui::badge :color="$changeColor" ring>{{ $change->status->label() }}</x-ui::badge>
                                </div>
                                <p class="mt-1 text-[12px] text-muted">
                                    {{ __('Demandé le') }} {{ $change->created_at->format('d/m/Y') }}
                                    @if ($change->isUpgrade() && $change->amount_cents)
                                        · {{ number_format($change->amount_cents / 100, 2, ',', ' ') }} EUR
                                    @endif
                                    @if ($change->applied_at)
                                        · {{ __('appliqué le') }} {{ $change->applied_at->format('d/m/Y') }}
                                    @endif
                                </p>
                                @if (! $change->isUpgrade() && $change->eligibility_confirmed_at)
                                    <p class="mt-1 text-[12px] text-muted">{{ __('Éligibilité attestée le') }} {{ $change->eligibility_confirmed_at->format('d/m/Y') }}@if ($change->effective_year) · {{ __('effet au 1er janvier :year', ['year' => $change->effective_year]) }}@endif</p>
                                @endif
                                @if ($change->note)
                                    <p class="mt-1 text-[12px] text-secondary">{{ __('Message au client :') }} {{ $change->note }}</p>
                                @endif
                                @if (! $change->isUpgrade() && $change->status === \App\Enums\Submission\PackChangeStatus::Requested)
                                    @php $downgradeRequests[] = $change; @endphp
                                    <div class="mt-3 space-y-2.5">
                                        <x-ui::textarea wire:model="packDowngradeNote" rows="2" :placeholder="__('Message au client en cas de refus (facultatif)')" />
                                        <div class="flex gap-2">
                                            <x-ui::button size="compact" class="flex-1" x-on:click="$dispatch('ui-open-modal', 'approve-downgrade-{{ $change->id }}')" :loading="true" target="approvePackDowngrade">{{ __('Valider') }}</x-ui::button>
                                            <x-ui::button variant="danger" size="compact" class="flex-1" x-on:click="$dispatch('ui-open-modal', 'reject-downgrade-{{ $change->id }}')" :loading="true" target="rejectPackDowngrade">{{ __('Refuser') }}</x-ui::button>
                                        </div>
                                    </div>
                                @endif
                                @if ($revertible)
                                    @php $revertibleUpgrades[] = $change; @endphp
                                    <x-ui::button variant="danger" size="compact" class="mt-2.5 w-full"
                                        x-on:click="$dispatch('ui-open-modal', 'revert-upgrade-{{ $change->id }}')" :loading="true" target="revertPackUpgrade">
                                        {{ __('Annuler le passage au Pro et rembourser') }}
                                    </x-ui::button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-admin.panel>
            @endif

            <x-admin.panel :title="__('Actions')">
                <div class="space-y-2.5 p-5">
                    <x-ui::button variant="secondary" x-on:click="$dispatch('ui-open-modal', 'send-email')" class="w-full">
                        <x-ui::icon name="envelope" class="h-4 w-4" />
                        {{ $isContact ? __('Répondre par email') : __('Envoyer un email') }}
                    </x-ui::button>
                    @if ($isOnlineJourney && $submission->resume_token && ! $isCancelled)
                        <x-ui::button variant="secondary" wire:click="resendLink" :loading="true" target="resendLink" class="w-full">
                            <x-ui::icon name="arrow-path" class="h-4 w-4" />
                            {{ $isPaid ? __('Renvoyer le lien du dossier') : __('Renvoyer le lien de reprise') }}
                        </x-ui::button>
                    @endif
                    @if ($isCancelled && $submission->resume_token)
                        {{-- Fin de la relation : le lien magique du client est ferme (ChangeSubmissionStatusAction). --}}
                        <p class="text-[12px] text-muted">{{ __('Accès client fermé (dossier annulé). Rouvrir le dossier rétablit le lien.') }}</p>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel :title="__('Zone sensible')" variant="danger">
                <div class="p-5">
                    <x-ui::button variant="danger" x-on:click="$dispatch('ui-open-modal', 'delete-dossier')" :loading="true" target="deleteDossier"
                        class="w-full">{{ $isContact ? __('Supprimer la prise de contact') : __('Supprimer le dossier') }}</x-ui::button>
                    <p class="mt-2 text-[12px] text-red-600/80 dark:text-red-400/80">{{ $isContact ? __('La prise de contact sera définitivement effacée (RGPD).') : __('Le dossier et tous ses fichiers seront définitivement effacés (RGPD).') }}</p>
                </div>
            </x-admin.panel>
        </aside>
    </div>

    {{-- Confirmations des actions sensibles. Le bouton de la fenetre lance l'action ; celui de la page
         ne fait que l'ouvrir. --}}
    @foreach ($downgradeRequests ?? [] as $change)
        <x-ui::modal name="approve-downgrade-{{ $change->id }}" variant="confirm" :title="__('Valider le passage au Creator au 1er janvier ?')">
            <p>{{ __('Le client sera prévenu par e-mail.') }}</p>

            <x-slot:actions>
                <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'approve-downgrade-{{ $change->id }}')">{{ __('Annuler') }}</x-ui::button>
                <x-ui::button wire:click="approvePackDowngrade({{ $change->id }})" x-on:click="$dispatch('ui-close-modal', 'approve-downgrade-{{ $change->id }}')">{{ __('Valider le passage') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::modal>

        <x-ui::modal name="reject-downgrade-{{ $change->id }}" variant="confirm" :title="__('Refuser le passage au Creator ?')">
            <p>{{ __('Le client sera prévenu par e-mail, avec votre message s\'il y en a un.') }}</p>

            <x-slot:actions>
                <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'reject-downgrade-{{ $change->id }}')">{{ __('Annuler') }}</x-ui::button>
                <x-ui::button variant="danger" wire:click="rejectPackDowngrade({{ $change->id }})" x-on:click="$dispatch('ui-close-modal', 'reject-downgrade-{{ $change->id }}')">{{ __('Refuser le passage') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::modal>
    @endforeach

    @foreach ($revertibleUpgrades ?? [] as $change)
        <x-ui::modal name="revert-upgrade-{{ $change->id }}" variant="confirm"
            :title="__('Annuler le passage au Pro et rembourser :amount EUR au client ?', ['amount' => number_format($change->amount_cents / 100, 2, ',', ' ')])">
            <p>{{ __('Le dossier reviendra au Creator avec son mandat Creator.') }}</p>

            <x-slot:actions>
                <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'revert-upgrade-{{ $change->id }}')">{{ __('Ne rien changer') }}</x-ui::button>
                <x-ui::button variant="danger" wire:click="revertPackUpgrade({{ $change->id }})" x-on:click="$dispatch('ui-close-modal', 'revert-upgrade-{{ $change->id }}')">{{ __('Annuler et rembourser') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::modal>
    @endforeach

    <x-ui::modal name="delete-dossier" variant="confirm" :title="$isContact ? __('Supprimer définitivement cette prise de contact ?') : __('Supprimer définitivement ce dossier et tous ses fichiers ?')">
        <p>{{ __('Action irréversible.') }}</p>

        <x-slot:actions>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'delete-dossier')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button variant="danger" wire:click="deleteDossier" x-on:click="$dispatch('ui-close-modal', 'delete-dossier')">{{ $isContact ? __('Supprimer la prise de contact') : __('Supprimer le dossier') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>

    <x-ui::modal name="send-email" :title="$isContact ? __('Répondre au message') : __('Envoyer un email au client')">
        <p class="mb-5 text-[13px] text-secondary">{{ __('À :') }} <span class="font-medium text-primary">{{ $submission->email }}</span></p>
        <form wire:submit="sendEmail" id="send-email-form" class="space-y-4">
            <x-ui::form-group :label="__('Objet')" for="email-subject" :error="$errors->first('emailSubject')">
                <x-ui::input id="email-subject" type="text" wire:model="emailSubject" :error="$errors->has('emailSubject')" />
            </x-ui::form-group>
            <x-ui::form-group :label="__('Message')" for="email-body" :error="$errors->first('emailBody')">
                <x-ui::textarea id="email-body" wire:model="emailBody" rows="7" :error="$errors->has('emailBody')" />
            </x-ui::form-group>
        </form>

        <x-slot:footer>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'send-email')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="submit" form="send-email-form" :loading="true" target="sendEmail">{{ __('Envoyer') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</div>
