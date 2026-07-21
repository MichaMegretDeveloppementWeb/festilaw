<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\AddSubmissionNoteAction;
use App\Actions\Admin\ChangeSubmissionStatusAction;
use App\Actions\Admin\IssueResponsiblePersonAction;
use App\Actions\Admin\SendAdminMessageAction;
use App\Actions\Admin\UploadCountersignedContractAction;
use App\Actions\Web\Starter\SendStarterResumeLinkAction;
use App\Enums\Billing\RenewalStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Models\Submission;
use App\Services\Billing\RenewalService;
use Illuminate\Support\Facades\Log;
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

    public function mount(Submission $submission): void
    {
        $this->submission = $submission->load([
            'contract', 'uploadedDocuments', 'payments', 'appointment', 'quizResult', 'notes.author',
        ]);
        $this->newStatus = $this->submission->status->value;
        $this->rpAddress = (string) $this->submission->eu_rp_address;
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
        if ($this->submission->type !== SubmissionType::Starter || (string) $this->submission->resume_token === '') {
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

    /** Dossier deja actif (souscription payee, non remboursee) : le lien mene a l'espace projet, pas a une reprise. */
    private function isPaid(): bool
    {
        return $this->submission->isActive();
    }

    /**
     * Statuts selectionnables par l'admin : tous sauf « Payé » (derive des paiements), plus le statut
     * courant s'il est deja Payé, pour que le menu reflete l'etat reel sans permettre de le forcer.
     *
     * @return array<int, SubmissionStatus>
     */
    private function assignableStatuses(): array
    {
        $statuses = array_values(array_filter(
            SubmissionStatus::cases(),
            fn (SubmissionStatus $status): bool => $status !== SubmissionStatus::Paid,
        ));

        if ($this->submission->status === SubmissionStatus::Paid) {
            array_unshift($statuses, SubmissionStatus::Paid);
        }

        return $statuses;
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
            'statuses' => $this->assignableStatuses(),
            'isStarter' => $this->submission->type === SubmissionType::Starter,
            'isContact' => $isContact,
            'isPaid' => $this->isPaid(),
            'renewal' => $renewal,
        ])->title(($isContact ? __('Prise de contact') : __('Dossier')).' '.$this->submission->reference.' · Festilaw');
    }

    /** Retour d'action ephemere cote client : un toast qui s'efface tout seul (cf. layout admin). */
    private function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('admin-toast', message: $message, type: $type);
    }
}
