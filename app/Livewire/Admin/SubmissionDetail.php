<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\AddSubmissionNoteAction;
use App\Actions\Admin\ChangeSubmissionStatusAction;
use App\Actions\Admin\IssueResponsiblePersonAction;
use App\Actions\Admin\SendAdminMessageAction;
use App\Actions\Admin\UpdateAppointmentAction;
use App\Actions\Admin\UploadCountersignedContractAction;
use App\Actions\Web\Payment\CheckPaymentStatusAction;
use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Actions\Web\Starter\SendStarterResumeLinkAction;
use App\Enums\Appointment\AppointmentStatus;
use App\Enums\Billing\RenewalStatus;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Billing\RenewalService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Back-office : detail d'un dossier et actions de traitement manuel (statut, email au client,
 * renvoi du lien, delivrance de la Personne Responsable UE, suppression RGPD).
 */
#[Layout('layouts.admin')]
class SubmissionDetail extends Component
{
    use HandlesAdminErrors;
    use WithFileUploads;

    public Submission $submission;

    public string $newStatus = '';

    public string $noteBody = '';

    public string $rpAddress = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    /** Fichier PDF du contrat contresigne (upload temporaire). */
    public $countersigned = null;

    /** Prevenir le client par email au depot du contrat contresigne. */
    public bool $notifyClientOnCountersign = true;

    /** Creneau confirme du rendez-vous SCALE (datetime-local), saisi par Festilaw. */
    public string $apptScheduledAt = '';

    /** Statut du rendez-vous SCALE (Demande / Programme / Termine / Annule). */
    public string $apptStatus = '';

    public function mount(Submission $submission): void
    {
        $this->submission = $submission->load([
            'contract', 'uploadedDocuments', 'payments', 'appointment', 'quizResult', 'notes.author',
        ]);
        $this->newStatus = $this->submission->status->value;
        $this->rpAddress = (string) $this->submission->eu_rp_address;

        if ($this->submission->appointment !== null) {
            $this->apptScheduledAt = $this->submission->appointment->scheduled_at?->format('Y-m-d\TH:i') ?? '';
            $this->apptStatus = $this->submission->appointment->status->value;
        }
    }

    public function updateStatus(ChangeSubmissionStatusAction $changeStatus): void
    {
        $status = SubmissionStatus::tryFrom($this->newStatus);

        if ($status === null) {
            $this->addError('newStatus', __('Statut invalide.'));

            return;
        }

        // « Payé » est derive des paiements (source de verite), jamais defini a la main : le forcer creerait
        // un cache mensonger (statut Paye sans paiement reussi). Seul un dossier deja Paye peut le conserver.
        if ($status === SubmissionStatus::Paid && $this->submission->status !== SubmissionStatus::Paid) {
            $this->addError('newStatus', __('Le statut « Payé » découle des paiements et ne peut pas être défini manuellement.'));

            return;
        }

        try {
            $changeStatus->execute($this->submission, $status);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin update submission status');

            return;
        }

        $this->submission->refresh();
        $this->toast(__('Statut mis à jour.'));
    }

    public function addNote(AddSubmissionNoteAction $addNote): void
    {
        $this->validate(
            ['noteBody' => ['required', 'string', 'max:5000']],
            [
                'noteBody.required' => __('Le contenu de la note est obligatoire.'),
                'noteBody.max' => __('La note ne peut pas dépasser 5000 caractères.'),
            ],
        );

        try {
            $addNote->execute($this->submission, $this->noteBody, auth()->id());
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin add submission note');

            return;
        }

        $this->noteBody = '';
        $this->submission->load('notes.author');
        $this->toast(__('Note ajoutée.'));
    }

    public function sendEmail(SendAdminMessageAction $sendMessage): void
    {
        $this->validate(
            [
                'emailSubject' => ['required', 'string', 'max:200'],
                'emailBody' => ['required', 'string', 'max:5000'],
            ],
            [
                'emailSubject.required' => __('L\'objet est obligatoire.'),
                'emailSubject.max' => __('L\'objet ne peut pas dépasser 200 caractères.'),
                'emailBody.required' => __('Le message est obligatoire.'),
                'emailBody.max' => __('Le message ne peut pas dépasser 5000 caractères.'),
            ],
        );

        try {
            $sendMessage->execute($this->submission, $this->emailSubject, $this->emailBody);
        } catch (Throwable $e) {
            Log::error('Unexpected error in Admin send message to client.', ['exception' => $e]);
            $this->toast(__('L\'envoi de l\'email a échoué. Réessayez.'), 'error');

            return;
        }

        $this->emailSubject = '';
        $this->emailBody = '';
        $this->dispatch('email-sent');
        $this->toast(__('Email envoyé au client.'));
    }

    public function resendLink(SendStarterResumeLinkAction $sendLink): void
    {
        // Ouvert a tous les parcours self-service (Creator ET Pro), pas au seul Starter.
        if (! $this->submission->type->hasOnlineJourney() || (string) $this->submission->resume_token === '') {
            return;
        }

        try {
            $sendLink->execute($this->submission);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin resend resume link');

            return;
        }

        $this->toast($this->isPaid()
            ? __('Lien du dossier renvoyé au client.')
            : __('Lien de reprise renvoyé au client.'));
    }

    /**
     * Re-interroge le prestataire (Stripe...) pour un paiement du dossier, a la demande du support. Si le
     * prestataire dit "paye", une fausse-echec est corrigee et le dossier reactive (source de verite).
     */
    public function recheckPayment(int $paymentId, CheckPaymentStatusAction $checkPaymentStatus): void
    {
        $payment = $this->submission->payments()->whereKey($paymentId)->first();
        if ($payment === null) {
            $this->toast(__('Paiement introuvable.'), 'error');

            return;
        }

        try {
            $result = $checkPaymentStatus->execute($payment);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin recheck payment');

            return;
        }

        $this->submission->refresh();
        $provider = $payment->providerLabel();

        if (! $result->reachable) {
            $this->toast(__(':provider n\'a pas pu être contacté pour ce paiement (session inconnue ou service indisponible). Réessayez plus tard.', ['provider' => $provider]), 'error');
        } elseif ($result->corrected) {
            // Sur un dossier annule, le paiement est corrige mais le dossier n'est PAS reactive (seul le
            // menu admin le peut) : ne pas annoncer une reactivation qui n'a pas eu lieu.
            $this->toast($this->submission->isActive()
                ? __(':provider confirme le paiement : corrigé en « Réussi » et dossier réactivé.', ['provider' => $provider])
                : __(':provider confirme le paiement : corrigé en « Réussi ». Le dossier reste annulé.', ['provider' => $provider]));
        } elseif ($result->confirmedPaid()) {
            $this->toast(__(':provider confirme que ce paiement est bien payé.', ['provider' => $provider]));
        } else {
            $this->toast(__(':provider ne confirme pas ce paiement comme payé (aucune correction).', ['provider' => $provider]), 'error');
        }
    }

    /** Dossier deja actif (souscription payee, non remboursee) : le lien mene a l'espace projet, pas a une reprise. */
    private function isPaid(): bool
    {
        return $this->submission->isActive();
    }

    /**
     * Statuts selectionnables par l'admin : tous sauf « Payé » (derive des paiements), plus le statut
     * courant s'il est deja Payé, pour que le menu reflete l'etat reel sans permettre de le forcer. Tries
     * par ordre d'etape du workflow (« Payé » reste a sa place, il ne remonte pas en tete du menu).
     *
     * @return array<int, SubmissionStatus>
     */
    private function assignableStatuses(): array
    {
        $statuses = array_filter(
            SubmissionStatus::cases(),
            fn (SubmissionStatus $status): bool => $status !== SubmissionStatus::Paid
                || $this->submission->status === SubmissionStatus::Paid,
        );

        usort($statuses, fn (SubmissionStatus $a, SubmissionStatus $b): int => $a->sortOrder() <=> $b->sortOrder());

        return array_values($statuses);
    }

    public function issueResponsiblePerson(IssueResponsiblePersonAction $issue): void
    {
        $this->validate(
            ['rpAddress' => ['required', 'string', 'max:1000']],
            [
                'rpAddress.required' => __('L\'adresse de la Personne Responsable est obligatoire.'),
                'rpAddress.max' => __('L\'adresse ne peut pas dépasser 1000 caractères.'),
            ],
        );

        try {
            $issue->execute($this->submission, $this->rpAddress);
        } catch (BaseAppException $e) {
            // Precondition metier non remplie (pas paye / mandat non signe / pieces manquantes) : message
            // clair a l'admin, aucun email envoye, dossier inchange.
            $this->toast(__($e->getUserMessage()), 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin issue Responsible Person');

            return;
        }

        $this->submission->refresh();
        $this->newStatus = $this->submission->status->value;
        $this->toast(__('Personne Responsable délivrée et client notifié.'));
    }

    public function uploadCountersigned(UploadCountersignedContractAction $upload): void
    {
        if ($this->submission->contract === null) {
            $this->addError('countersigned', __('Ce dossier n\'a pas de contrat.'));

            return;
        }

        // On ne contresigne que ce que le client a signe : refuser un depot sur un mandat non signe
        // (en attente / refuse / expire), sinon on notifierait un client d'un contrat jamais signe.
        if ($this->submission->contract->signature_status !== SignatureStatus::Signed) {
            $this->addError('countersigned', __('Le client doit d\'abord signer le mandat avant de pouvoir déposer le contrat contresigné.'));

            return;
        }

        $this->validate(
            ['countersigned' => ['required', 'file', 'mimes:pdf', 'max:10240']],
            [
                'countersigned.required' => __('Sélectionnez le PDF du contrat contresigné.'),
                'countersigned.mimes' => __('Le contrat contresigné doit être un fichier PDF.'),
                'countersigned.max' => __('Le fichier ne peut pas dépasser 10 Mo.'),
            ],
        );

        try {
            $path = $this->countersigned->storeAs('contracts/countersigned', $this->submission->id.'.pdf', 'local');
            $upload->execute($this->submission, $path, $this->notifyClientOnCountersign);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin upload countersigned contract');

            return;
        }

        $this->reset('countersigned');
        $this->submission->load('contract');
        $this->toast($this->notifyClientOnCountersign
            ? __('Contrat contresigné ajouté et client notifié.')
            : __('Contrat contresigné ajouté.'));
    }

    /**
     * Rattrapage manuel du PDF d'un mandat DEJA signe dont le fichier local est manquant (echec transitoire
     * du prestataire au moment de la signature : le PDF fusionne n'etait pas encore pret). Re-telecharge le
     * fichier sans toucher NI au statut NI au parcours. Complement immediat de la reconciliation automatique
     * (festilaw:reconcile-signatures), pour le support qui constate "signe mais pas de document".
     */
    public function recoverSignedMandate(MarkContractSignedAction $markSigned): void
    {
        $contract = $this->submission->contract;

        // Ne s'applique qu'a un mandat signe sans PDF local : sinon rien a rattraper.
        if ($contract === null
            || $contract->signature_status !== SignatureStatus::Signed
            || $contract->signed_file_path !== null) {
            return;
        }

        try {
            $markSigned->backfillSignedDocument($contract);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin recover signed mandate');

            return;
        }

        $this->submission->load('contract');

        $this->toast($this->submission->contract->signed_file_path !== null
            ? __('Mandat signé récupéré.')
            : __('Le PDF signé n\'a pas pu être récupéré (le prestataire ne l\'a pas encore fourni). Réessayez dans quelques instants.'), $this->submission->contract->signed_file_path !== null ? 'success' : 'error');
    }

    /**
     * Rendez-vous SCALE : Festilaw saisit le creneau confirme (aucun webhook agenda Google) et fait
     * avancer le statut (Demande -> Programme -> Termine / Annule).
     */
    public function updateAppointment(UpdateAppointmentAction $updateAppointment): void
    {
        if ($this->submission->appointment === null) {
            $this->addError('apptStatus', __('Ce dossier n\'a pas de rendez-vous.'));

            return;
        }

        $validated = $this->validate([
            'apptScheduledAt' => ['nullable', 'date'],
            'apptStatus' => ['required', Rule::enum(AppointmentStatus::class)],
        ], [
            'apptStatus.required' => __('Le statut du rendez-vous est obligatoire.'),
        ]);

        $scheduledAt = ($validated['apptScheduledAt'] ?? '') !== ''
            ? CarbonImmutable::parse($validated['apptScheduledAt'])
            : null;

        try {
            $updateAppointment->execute(
                $this->submission->appointment,
                $scheduledAt,
                AppointmentStatus::from($validated['apptStatus']),
            );
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin update appointment');

            return;
        }

        $this->submission->load('appointment');
        $this->toast(__('Rendez-vous mis à jour.'));
    }

    /** Un dossier SCALE dont l'audit (75 EUR) est paye : le badge "a deduire du devis" s'affiche. */
    private function scaleAuditPaid(): bool
    {
        return $this->submission->payments->contains(
            fn (Payment $payment): bool => $payment->type === PaymentType::ScaleAudit && $payment->status === PaymentStatus::Succeeded,
        );
    }

    public function deleteDossier(): mixed
    {
        $isContact = $this->submission->type === SubmissionType::Contact;

        try {
            $this->submission->delete();
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin delete dossier');

            return null;
        }

        session()->flash('admin_flash', $isContact ? __('Prise de contact supprimée.') : __('Dossier supprimé.'));

        return $this->redirectRoute($isContact ? 'admin.contacts.index' : 'admin.submissions.index', navigate: true);
    }

    public function render(RenewalService $renewals): View
    {
        $isContact = $this->submission->type === SubmissionType::Contact;

        $renewal = null;
        if ($this->submission->type->hasOnlineJourney() && $this->isPaid()) {
            $status = $renewals->status($this->submission);
            $renewal = [
                'label' => $status->label(),
                'severity' => match ($status) {
                    RenewalStatus::UpToDate => 'ok',
                    RenewalStatus::Due => 'warn',
                    RenewalStatus::Overdue => 'bad',
                },
                'paidThroughYear' => $renewals->paidThroughYear($this->submission),
                'nextRenewalDate' => $renewals->nextRenewalDate($this->submission),
            ];
        }

        return view('livewire.admin.submission-detail', [
            'dossierState' => $renewals->state($this->submission),
            'statuses' => $this->assignableStatuses(),
            'isOnlineJourney' => $this->submission->type->hasOnlineJourney(),
            'isContact' => $isContact,
            'isPaid' => $this->isPaid(),
            'renewal' => $renewal,
            'isScale' => $this->submission->type === SubmissionType::Scale,
            'scaleAuditPaid' => $this->scaleAuditPaid(),
            'appointmentStatuses' => AppointmentStatus::cases(),
        ])->title(($isContact ? __('Prise de contact') : __('Dossier')).' '.$this->submission->reference.' · Festilaw');
    }

    /** Retour d'action ephemere cote client : un toast qui s'efface tout seul (cf. layout admin). */
    private function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('admin-toast', message: $message, type: $type);
    }
}
