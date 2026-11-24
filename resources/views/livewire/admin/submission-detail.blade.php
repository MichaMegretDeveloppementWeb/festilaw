{{-- Fiche d'un dossier ou d'une prise de contact, sur page blanche (decision de Micha du 16/11/2026).
     En-tete : la reference (ou le nom du contact), l'etat par un point de couleur et son libelle, les actions et un
     menu ⋯ qui porte la suppression. Puis la bande des chiffres du dossier, et deux colonnes : a gauche ce qu'on
     consulte (client, contrat, pieces, paiements, notes), a droite les formulaires de gestion (statut,
     Personne Responsable, changement de pack, rendez-vous), separes par un filet vertical, sans carte.
     Une prise de contact n'a pas de colonne de droite. Les boutons de la page n'ouvrent que les fenetres de
     confirmation ; ce sont les boutons des fenetres qui lancent les actions. --}}
@php
    $stateTone = $isContact ? 'text-amber-700 dark:text-amber-400' : match ($dossierState->severity()) {
        'ok' => 'text-emerald-700 dark:text-emerald-400',
        'warn' => 'text-amber-700 dark:text-amber-400',
        'bad' => 'text-red-700 dark:text-red-400',
        'neutral' => 'text-blue-700 dark:text-blue-400',
        default => 'text-secondary',
    };
    $hasSidebar = ! $isContact || $submission->appointment;
    $lastPayment = $isContact ? null : $submission->payments->sortByDesc('id')->first();
@endphp
<div x-data @email-sent.window="$dispatch('ui-close-modal', 'send-email')">
    <a href="{{ $isContact ? route('admin.contacts.index') : route('admin.submissions.index') }}" class="inline-flex items-center gap-1.5 text-[13px] text-muted transition-colors hover:text-primary">
        <x-ui::icon name="arrow-left" class="h-3.5 w-3.5" /> {{ $isContact ? __('Prises de contact') : __('Dossiers') }}
    </a>

    {{-- En-tete --}}
    <div class="mb-8 mt-2 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <h1 class="text-[22px] font-semibold leading-7 tracking-tight text-primary">{{ $isContact ? ($submission->first_name ?: __('Contact sans nom')) : $submission->reference }}</h1>
                <span class="inline-flex items-center gap-2 text-[12px] font-semibold uppercase tracking-[0.08em] {{ $stateTone }}" data-dossier-state>
                    <span class="h-2 w-2 rounded-full bg-current" aria-hidden="true"></span>
                    {{ $isContact ? __('Prise de contact') : $dossierState->label() }}
                </span>
            </div>
            @if ($isContact)
                <p class="mt-1 text-[13px] text-secondary">{{ __('Reçue le :date via le formulaire de contact, réf. :reference', ['date' => $submission->created_at->format('d/m/Y à H:i'), 'reference' => $submission->reference]) }}</p>
                {{-- Politique de confidentialite : suppression 12 mois apres le dernier echange (ApplyPrivacyRetention). --}}
                <p class="mt-1 text-[12px] text-muted">{{ __('Suppression automatique le :date, sans nouvel échange d\'ici là. Une note ou un e-mail envoyé d\'ici repousse ce délai ; les réponses envoyées depuis votre boîte mail ne comptent pas.', ['date' => $contactDeletionDate->format('d/m/Y')]) }}</p>
            @else
                <p class="mt-1 text-[13px] text-secondary">{{ $submission->type->label() }}, {{ __('créé le :date', ['date' => $submission->created_at->format('d/m/Y à H:i')]) }}</p>
                @if ($isCancelled && $submission->resume_token)
                    {{-- Fin de la relation : le lien magique du client est ferme (ChangeSubmissionStatusAction). --}}
                    <p class="mt-1 text-[12px] text-muted">{{ __('Accès client fermé (dossier annulé). Rouvrir le dossier rétablit le lien.') }}</p>
                @endif
            @endif
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <x-ui::button variant="secondary" x-on:click="$dispatch('ui-open-modal', 'send-email')" data-send-email>
                <x-ui::icon name="envelope" />
                {{ $isContact ? __('Répondre par email') : __('Envoyer un email') }}
            </x-ui::button>
            @if ($isOnlineJourney && $submission->resume_token && ! $isCancelled)
                <x-ui::button variant="secondary" wire:click="resendLink" :loading="true" target="resendLink">
                    <x-ui::icon name="arrow-path" />
                    {{ $isPaid ? __('Renvoyer le lien du dossier') : __('Renvoyer le lien de reprise') }}
                </x-ui::button>
            @endif

            {{-- La suppression est dans le menu : elle n'ouvre que la fenetre de confirmation. --}}
            <x-ui::dropdown align="right" width="w-64">
                <x-slot:trigger>
                    <x-ui::button variant="secondary" aria-haspopup="menu" :aria-label="__('Autres actions')" data-dossier-menu>
                        <x-ui::icon name="ellipsis-horizontal" class="h-[18px] w-[18px]" />
                    </x-ui::button>
                </x-slot:trigger>
                <x-ui::dropdown-item icon="trash" :danger="true" data-delete-dossier x-on:click="$dispatch('ui-open-modal', 'delete-dossier')">
                    {{ $isContact ? __('Supprimer la prise de contact') : __('Supprimer le dossier') }}
                </x-ui::dropdown-item>
            </x-ui::dropdown>
        </div>
    </div>

    {{-- Changement de pack avant paiement : l'ancien dossier (annule) et son remplacant se pointent l'un l'autre. --}}
    @unless ($isContact)
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

        {{-- Les chiffres du dossier : tout vient des donnees deja chargees, aucune requete de plus. --}}
        <x-admin.stats :columns="4" class="mb-12" data-dossier-facts>
            <x-admin.stats.item icon="rectangle-stack" :label="__('Pack')" size="sm">{{ $submission->type->label() }}</x-admin.stats.item>
            <x-admin.stats.item icon="pencil-square" :label="__('Signature')" size="sm">{{ $submission->contract?->signature_status->label() ?? __('Pas encore de contrat') }}</x-admin.stats.item>
            <x-admin.stats.item icon="credit-card" :label="__('Paiement')" size="sm">
                @if ($lastPayment)
                    {{ number_format($lastPayment->amount_cents / 100, 2, ',', ' ') }} {{ $lastPayment->currency }}
                    <x-slot:extra>{{ $lastPayment->status->label() }}</x-slot:extra>
                @else
                    {{ __('Aucun paiement') }}
                @endif
            </x-admin.stats.item>
            <x-admin.stats.item icon="calendar-days" :label="__('Renouvellement')" size="sm">
                @if ($renewal)
                    <span @class(['inline-flex items-center gap-2', 'text-emerald-700 dark:text-emerald-400' => $renewal['severity'] === 'ok', 'text-amber-700 dark:text-amber-400' => $renewal['severity'] === 'warn', 'text-red-700 dark:text-red-400' => $renewal['severity'] === 'bad'])>
                        <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $renewal['label'] }}
                    </span>
                    <x-slot:extra>
                        {{ __('Payé jusqu\'à l\'année :year', ['year' => $renewal['paidThroughYear'] ?? '-']) }}@if ($renewal['nextRenewalDate']), {{ __('prochain le :date', ['date' => $renewal['nextRenewalDate']->format('d/m/Y')]) }}@endif
                    </x-slot:extra>
                @else
                    –
                @endif
            </x-admin.stats.item>
        </x-admin.stats>
    @endunless

    <div @class(['grid items-start gap-y-12', 'lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]' => $hasSidebar])>
        {{-- Ce qu'on consulte --}}
        <div @class(['min-w-0', 'lg:pr-8' => $hasSidebar])>
            {{-- Le client en fiche de contact : qui (entreprise, personne), puis une ligne par moyen de le joindre,
                 cliquable, puis les donnees secondaires sur une ligne grise. Pas de seconde grille sous celle des
                 chiffres du dossier (retour de Micha du 16/11 au soir). --}}
            @php
                $localeNames = ['en' => __('Anglais'), 'fr' => __('Français'), 'es' => __('Espagnol')];
                $localeName = mb_strtolower($localeNames[(string) $submission->locale] ?? strtoupper((string) $submission->locale));
                $personName = $isContact ? ($submission->first_name ?: null) : (trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: null);
                $website = $submission->website_url ?: null;
                $websiteHref = $website !== null && ! preg_match('#^https?://#i', $website) ? 'https://'.$website : $website;
                $phoneHref = $submission->phone ? preg_replace('/[^\d+]/', '', $submission->phone) : null;
                $contactMeta = $isContact
                    ? [__('Message en :language', ['language' => $localeName]), __('Reçu le :date', ['date' => $submission->created_at->format('d/m/Y à H:i')])]
                    : [
                        __('Dossier en :language', ['language' => $localeName]),
                        __('Créé le :date', ['date' => $submission->created_at->format('d/m/Y à H:i')]),
                        $submission->company_registration_number
                            ? __('N° d\'immatriculation :number', ['number' => $submission->company_registration_number])
                            : __('N° d\'immatriculation non renseigné'),
                    ];
            @endphp
            <x-admin.section :title="$isContact ? __('Coordonnées') : __('Client')" data-dossier-client>
                <div class="pt-5">
                    {{-- Le nom du client en plus grand que ses coordonnees (Micha, 24/11), sous le titre de section. --}}
                    @unless ($isContact)
                        <p class="text-[17px] font-semibold leading-6 text-primary">{{ $submission->company_name ?: __('Entreprise non renseignée') }}</p>
                    @endunless
                    <p @class(['mt-0.5 text-[15px] text-secondary' => ! $isContact, 'text-[17px] font-semibold leading-6 text-primary' => $isContact])>{{ $personName ?? __('Nom non renseigné') }}</p>

                    <ul class="mt-4 space-y-2.5 text-[14px]" data-contact-lines>
                        <li class="flex min-w-0 items-center gap-2.5">
                            <x-ui::icon name="envelope" class="h-4 w-4 shrink-0 text-muted" />
                            <a href="mailto:{{ $submission->email }}" class="min-w-0 break-all text-primary hover:underline">{{ $submission->email }}</a>
                        </li>
                        @unless ($isContact)
                            <li class="flex min-w-0 items-center gap-2.5">
                                <x-ui::icon name="phone" class="h-4 w-4 shrink-0 text-muted" />
                                @if ($phoneHref)
                                    <a href="tel:{{ $phoneHref }}" class="text-primary hover:underline">{{ $submission->phone }}</a>
                                @else
                                    <span class="text-muted">{{ __('Téléphone non renseigné') }}</span>
                                @endif
                            </li>
                        @endunless
                        <li class="flex min-w-0 items-center gap-2.5">
                            <x-ui::icon name="globe-alt" class="h-4 w-4 shrink-0 text-muted" />
                            @if ($website)
                                <a href="{{ $websiteHref }}" target="_blank" rel="noopener" class="min-w-0 break-all text-primary hover:underline">{{ $website }}</a>
                            @else
                                <span class="text-muted">{{ __('Site web non renseigné') }}</span>
                            @endif
                        </li>
                    </ul>

                    <p class="mt-4 text-[12px] text-muted" data-contact-meta>{{ implode(' · ', $contactMeta) }}</p>
                </div>
            </x-admin.section>

            @if ($submission->message)
                <x-admin.section :title="__('Message')">
                    <p class="max-w-3xl whitespace-pre-wrap pt-5 text-[14px] leading-relaxed text-secondary">{{ $submission->message }}</p>
                </x-admin.section>
            @endif

            @if ($submission->contract)
                @php
                    $hasCountersigned = (bool) $submission->contract->countersigned_file_path;
                    $previousMandates = $submission->contracts->where('role', \App\Enums\Contract\ContractRole::Superseded)->whereNotNull('signed_file_path');
                    $mandateSummary = __('Contrat :pack', ['pack' => $submission->contract->packType()->label()]).', '.mb_strtolower($submission->contract->signature_status->label())
                        .($submission->contract->signature_provider ? ', '.__('via :provider', ['provider' => $submission->contract->signature_provider]) : '').'.';
                @endphp
                <x-admin.section :title="__('Contrat')" :description="$mandateSummary" data-dossier-mandate>
                    <ul class="divide-y divide-[var(--ui-border)] border-b border-default">
                        @if ($submission->contract->signed_file_path)
                            <li>
                                <x-admin.document-item :title="__('Signé par le client')"
                                    :subtitle="$submission->contract->signed_at ? __('Signé le').' '.$submission->contract->signed_at->format('d/m/Y') : __('Document PDF')"
                                    :download-url="route('admin.submissions.mandate', ['submission' => $submission->id])" />
                            </li>
                        @elseif ($submission->contract->signature_status === \App\Enums\Contract\SignatureStatus::Signed)
                            {{-- Contrat signe mais PDF local absent (echec transitoire du prestataire) : recuperation manuelle immediate. --}}
                            <li class="flex flex-col gap-3 bg-amber-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5 dark:bg-amber-950/30">
                                <div class="flex items-start gap-2.5">
                                    <x-ui::icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                                    <div>
                                        <p class="text-[13px] font-medium text-primary">{{ __('Contrat signé, PDF manquant') }}</p>
                                        <p class="text-[12px] text-muted">{{ __('La signature a bien eu lieu mais le PDF n\'a pas pu être téléchargé. Récupérez-le maintenant.') }}</p>
                                    </div>
                                </div>
                                <x-ui::button variant="secondary" size="compact" wire:click="recoverSignedMandate" :loading="true" target="recoverSignedMandate" class="shrink-0">
                                    <x-ui::icon name="arrow-down-tray" class="h-3.5 w-3.5" />
                                    {{ __('Récupérer le PDF signé') }}
                                </x-ui::button>
                            </li>
                        @endif

                        {{-- Contrat contresigne par Festilaw (contre-signature manuelle, hors SignWell) --}}
                        @if ($hasCountersigned)
                            <li>
                                <x-admin.document-item :title="__('Contresigné par Festilaw')"
                                    :subtitle="__('Déposé le').' '.$submission->contract->countersigned_at?->format('d/m/Y à H:i')"
                                    :download-url="route('admin.submissions.countersigned', ['submission' => $submission->id])" />
                            </li>
                        @else
                            <li class="py-3.5 text-[13px] text-muted">{{ __('Aucun contrat contresigné déposé pour le moment.') }}</li>
                        @endif

                        {{-- Contrats remplaces par un changement de pack apres paiement (SC12) : conserves comme trace. --}}
                        @foreach ($previousMandates as $previous)
                            <li>
                                <x-admin.document-item :title="__('Contrat :pack', ['pack' => $previous->packType()->label()])"
                                    :subtitle="__('Contrat précédent, signé le').' '.$previous->signed_at?->format('d/m/Y').($previous->superseded_at ? ', '.__('remplacé le').' '.$previous->superseded_at->format('d/m/Y') : '')"
                                    :download-url="route('admin.submissions.contract-mandate', ['submission' => $submission->id, 'contract' => $previous->id])" />
                            </li>
                        @endforeach
                    </ul>

                    <form wire:submit="uploadCountersigned" class="max-w-2xl space-y-3 pt-6">
                        <p class="text-[13px] font-medium text-primary">
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
                </x-admin.section>
            @endif

            @unless ($isContact)
                <x-admin.section :title="__('Pièces')" data-dossier-documents>
                    <x-slot:actions>{{ trans_choice('{0} Aucune pièce|{1} 1 pièce|[2,*] :count pièces', $submission->uploadedDocuments->count(), ['count' => $submission->uploadedDocuments->count()]) }}</x-slot:actions>

                    @if ($submission->uploadedDocuments->isEmpty())
                        <p class="pt-5 text-[13px] text-secondary">{{ __('Aucune pièce téléversée.') }}</p>
                    @else
                        <ul class="divide-y divide-[var(--ui-border)] border-b border-default">
                            @foreach ($submission->uploadedDocuments as $doc)
                                <li wire:key="doc-{{ $doc->id }}">
                                    <x-admin.document-item :title="$doc->type->label()" :subtitle="$doc->original_filename"
                                        :download-url="route('admin.submissions.document', ['submission' => $submission->id, 'document' => $doc->id])" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-admin.section>

                {{-- Paiements : affiches meme sans paiement, pour que l'etat soit clair. --}}
                <x-admin.section :title="__('Paiements')" data-dossier-payments>
                    @if ($submission->payments->isEmpty())
                        <p class="pt-5 text-[13px] text-secondary">{{ __('Aucun paiement enregistré pour ce dossier.') }}</p>
                    @else
                        <ul class="divide-y divide-[var(--ui-border)] border-b border-default">
                            @foreach ($submission->payments as $payment)
                                @php
                                    $settled = in_array($payment->status, [\App\Enums\Payment\PaymentStatus::Succeeded, \App\Enums\Payment\PaymentStatus::Refunded], true);
                                    $payColor = match ($payment->status) {
                                        \App\Enums\Payment\PaymentStatus::Succeeded => 'emerald',
                                        \App\Enums\Payment\PaymentStatus::Failed => 'red',
                                        \App\Enums\Payment\PaymentStatus::Pending, \App\Enums\Payment\PaymentStatus::Processing => 'amber',
                                        default => 'gray',
                                    };
                                @endphp
                                <li wire:key="pay-{{ $payment->id }}" class="grid gap-x-6 gap-y-2 py-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                            <span class="text-[15px] font-semibold tabular-nums text-primary">{{ number_format($payment->amount_cents / 100, 2, ',', ' ') }} {{ $payment->currency }}</span>
                                            <x-ui::badge :color="$payColor" dot>{{ $payment->status->label() }}</x-ui::badge>
                                        </div>
                                        <p class="mt-1 text-[12px] text-muted">
                                            {{ $payment->type->label() }}{{ $payment->service_year ? ', '.$payment->service_year : '' }},
                                            {{ __('payé le') }} {{ $payment->paid_at?->format('d/m/Y à H:i') ?: '—' }}
                                        </p>
                                        <p class="mt-0.5 break-all font-mono text-[11px] text-muted">{{ $payment->provider }} · {{ $payment->provider_reference ?: '—' }}</p>
                                    </div>
                                    @unless ($settled)
                                        <div class="sm:self-center">
                                            <x-ui::button variant="secondary" size="compact" wire:click="recheckPayment({{ $payment->id }})" :loading="true" target="recheckPayment({{ $payment->id }})">
                                                <x-ui::icon name="arrow-path" class="h-3.5 w-3.5" />
                                                {{ __('Vérifier sur :provider', ['provider' => $payment->providerLabel()]) }}
                                            </x-ui::button>
                                        </div>
                                    @endunless
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-admin.section>
            @endunless

            @if ($isScale)
                <x-admin.section :title="__('Audit Scale')">
                    <div class="pt-5">
                        @if ($scaleAuditPaid)
                            <x-ui::badge color="emerald" ring>{{ __('Audit 75 € payé · à déduire du devis final') }}</x-ui::badge>
                        @elseif ($submission->appointment)
                            {{-- La consultation se reserve avant le paiement : un creneau peut etre pris sans audit regle. --}}
                            <x-ui::badge color="amber" ring>{{ __('Réservé · audit non payé') }}</x-ui::badge>
                            <p class="mt-2 text-[13px] text-secondary">{{ __('Le client a réservé un créneau mais n\'a pas encore réglé l\'audit. S\'il ne le règle pas, annulez le créneau dans Google Agenda.') }}</p>
                        @else
                            <x-ui::badge color="gray" ring>{{ __('Audit non payé') }}</x-ui::badge>
                        @endif
                    </div>
                </x-admin.section>
            @endif

            <x-admin.section :title="__('Notes internes')" data-dossier-notes>
                <form wire:submit="addNote" class="max-w-3xl pt-5">
                    <x-ui::form-group :error="$errors->first('noteBody')">
                        <x-ui::textarea wire:model="noteBody" rows="3" :placeholder="__('Ajouter une note de suivi...')" :error="$errors->has('noteBody')" />
                    </x-ui::form-group>
                    <x-ui::button type="submit" :loading="true" target="addNote" size="compact" class="mt-2.5">{{ __('Ajouter la note') }}</x-ui::button>
                </form>
                @if ($submission->notes->isEmpty())
                    <p class="pt-5 text-[13px] text-secondary">{{ __('Aucune note pour le moment.') }}</p>
                @else
                    <ul class="mt-6 divide-y divide-[var(--ui-border)] border-y border-default">
                        @foreach ($submission->notes as $note)
                            <li class="py-4" wire:key="note-{{ $note->id }}">
                                <p class="text-[12px] text-muted">{{ $note->author?->name ?: __('Équipe') }}, {{ $note->created_at->format('d/m/Y à H:i') }}</p>
                                <p class="mt-1 max-w-3xl whitespace-pre-wrap text-[14px] leading-relaxed text-secondary">{{ $note->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.section>
        </div>

        {{-- Ce qu'on fait : les formulaires de gestion, separes par des filets, colles en haut au defilement. --}}
        @if ($hasSidebar)
            <aside class="min-w-0 lg:sticky lg:top-6 lg:self-start lg:border-l lg:border-default lg:pl-8 [&>*+*]:mt-6 [&>*+*]:border-t [&>*+*]:border-default [&>*+*]:pt-6" data-dossier-manage>
                @unless ($isContact)
                    <section>
                        <h2 class="text-[15px] font-semibold leading-snug text-primary">{{ __('Statut du dossier') }}</h2>
                        <form wire:submit="updateStatus" class="mt-3">
                            <x-ui::form-group :label="__('Changer le statut')" for="new-status" class="mb-3" :error="$errors->first('newStatus')">
                                <x-ui::select id="new-status" wire:model="newStatus" :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :error="$errors->has('newStatus')" />
                            </x-ui::form-group>
                            <x-ui::button type="submit" :loading="true" target="updateStatus" class="w-full">{{ __('Enregistrer le statut') }}</x-ui::button>
                        </form>
                    </section>
                @endunless

                @if ($isOnlineJourney)
                    <section>
                        <h2 class="text-[15px] font-semibold leading-snug text-primary">{{ __('Personne Responsable UE') }}</h2>
                        <form wire:submit="issueResponsiblePerson" class="mt-3">
                            <x-ui::form-group :label="__('Adresse délivrée')" for="rp-address" class="mb-3" :error="$errors->first('rpAddress')">
                                <x-ui::textarea id="rp-address" wire:model="rpAddress" rows="3" :placeholder="__('Adresse officielle de représentation dans l\'UE...')" :error="$errors->has('rpAddress')" />
                            </x-ui::form-group>
                            <x-ui::button type="submit" :loading="true" target="issueResponsiblePerson" class="w-full">{{ __('Émettre et terminer') }}</x-ui::button>
                        </form>
                    </section>
                @endif
            {{-- Changements de pack apres paiement (SC12) : historique, et le "dernier mot" de Festilaw. --}}
            @if ($submission->packChanges->isNotEmpty())
                <section data-dossier-pack-changes>
                    <h2 class="text-[15px] font-semibold leading-snug text-primary">{{ __('Changement de pack') }}</h2>
                    {{-- Les changements qui attendent une confirmation : leurs fenetres sont rendues en bas de page. --}}
                    @php $downgradeRequests = $revertibleUpgrades = []; @endphp
                    <div class="-mt-1 divide-y divide-[var(--ui-border)]">
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
                </section>
            @endif

            @if ($submission->appointment)
                <section>
                    <h2 class="text-[15px] font-semibold leading-snug text-primary">{{ __('Rendez-vous') }}</h2>
                    <form wire:submit="updateAppointment" class="mt-3 space-y-4">
                        <x-ui::form-group :label="__('Créneau confirmé')" for="appt-scheduled" :error="$errors->first('apptScheduledAt')">
                            <x-ui::input type="datetime-local" id="appt-scheduled" wire:model="apptScheduledAt" :error="$errors->has('apptScheduledAt')" />
                        </x-ui::form-group>
                        <x-ui::form-group :label="__('Statut')" for="appt-status" :error="$errors->first('apptStatus')">
                            <x-ui::select id="appt-status" wire:model="apptStatus" :options="collect($appointmentStatuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :error="$errors->has('apptStatus')" />
                        </x-ui::form-group>
                        <x-ui::button type="submit" :loading="true" target="updateAppointment" class="w-full">{{ __('Enregistrer le rendez-vous') }}</x-ui::button>
                    </form>
                </section>
            @endif
            </aside>
        @endif
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
            <p>{{ __('Le dossier reviendra au Creator avec son contrat Creator.') }}</p>

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
