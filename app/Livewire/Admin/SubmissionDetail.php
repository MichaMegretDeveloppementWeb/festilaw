<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\AddSubmissionNoteAction;
use App\Actions\Admin\ChangeSubmissionStatusAction;
use App\Actions\Admin\IssueResponsiblePersonAction;
use App\Actions\Admin\SendAdminMessageAction;
use App\Actions\Web\Starter\SendStarterResumeLinkAction;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Back-office : detail d'un dossier (infos, contrat/signature, pieces, paiements, RDV, notes internes)
 * et actions de traitement manuel : changer le statut, ecrire au client, renvoyer le lien de reprise,
 * delivrer l'adresse de Personne Responsable UE (finalisation), supprimer le dossier (RGPD). Modele
 * charge avec ses relations dans mount (pas de N+1). Route par id (getRouteKeyName = resume_token cote
 * public). Les retours d'action passent par un toast ephemere (evenement admin-toast).
 */
#[Layout('layouts.admin')]
class SubmissionDetail extends Component
{
    public Submission $submission;

    public string $newStatus = '';

    public string $noteBody = '';

    public string $rpAddress = '';

    public string $emailSubject = '';

    public string $emailBody = '';

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

        $changeStatus->execute($this->submission, $status);
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

        $addNote->execute($this->submission, $this->noteBody, auth()->id());
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
        } catch (Throwable) {
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

        $sendLink->execute($this->submission);
        $this->toast($this->isPaid()
            ? __('Lien du dossier renvoyé au client.')
            : __('Lien de reprise renvoyé au client.'));
    }

    /** Dossier deja actif (paye ou finalise) : le lien mene a l'espace projet, pas a une reprise. */
    private function isPaid(): bool
    {
        return in_array($this->submission->status, [SubmissionStatus::Paid, SubmissionStatus::Completed], true);
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

        $issue->execute($this->submission, $this->rpAddress);
        $this->submission->refresh();
        $this->newStatus = $this->submission->status->value;
        $this->toast(__('Personne Responsable délivrée et client notifié.'));
    }

    public function deleteDossier(): mixed
    {
        $this->submission->delete();
        session()->flash('admin_flash', __('Dossier supprimé.'));

        return $this->redirectRoute('admin.submissions.index', navigate: true);
    }

    public function render(): View
    {
        $isContact = $this->submission->type === SubmissionType::Contact;

        return view('livewire.admin.submission-detail', [
            'statuses' => SubmissionStatus::cases(),
            'isStarter' => $this->submission->type === SubmissionType::Starter,
            'isContact' => $isContact,
            'isPaid' => $this->isPaid(),
        ])->title(($isContact ? __('Prise de contact') : __('Dossier')).' '.$this->submission->reference.' · Festilaw');
    }

    /** Retour d'action ephemere cote client : un toast qui s'efface tout seul (cf. layout admin). */
    private function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('admin-toast', message: $message, type: $type);
    }
}
