<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\AddSubmissionNoteAction;
use App\Actions\Admin\ChangeSubmissionStatusAction;
use App\Actions\Admin\IssueResponsiblePersonAction;
use App\Actions\Web\Starter\SendStarterResumeLinkAction;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Back-office : detail d'un dossier (infos, contrat/signature, pieces, paiements, RDV, notes internes)
 * et actions de traitement manuel : changer le statut, renvoyer le lien de reprise, delivrer l'adresse
 * de Personne Responsable UE (finalisation), supprimer le dossier (RGPD). Modele charge avec ses
 * relations dans mount (pas de N+1). Route par id (getRouteKeyName = resume_token cote public).
 */
#[Layout('layouts.admin')]
class SubmissionDetail extends Component
{
    public Submission $submission;

    public string $newStatus = '';

    public string $noteBody = '';

    public string $rpAddress = '';

    public string $flash = '';

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
        $this->flash = __('Statut mis à jour.');
    }

    public function addNote(AddSubmissionNoteAction $addNote): void
    {
        $this->validate(['noteBody' => ['required', 'string', 'max:5000']]);

        $addNote->execute($this->submission, $this->noteBody, auth()->id());
        $this->noteBody = '';
        $this->submission->load('notes.author');
        $this->flash = __('Note ajoutée.');
    }

    public function resendLink(SendStarterResumeLinkAction $sendLink): void
    {
        if ($this->submission->type !== SubmissionType::Starter || (string) $this->submission->resume_token === '') {
            return;
        }

        $sendLink->execute($this->submission);
        $this->flash = __('Lien de reprise renvoyé au client.');
    }

    public function issueResponsiblePerson(IssueResponsiblePersonAction $issue): void
    {
        $this->validate(['rpAddress' => ['required', 'string', 'max:1000']]);

        $issue->execute($this->submission, $this->rpAddress);
        $this->submission->refresh();
        $this->newStatus = $this->submission->status->value;
        $this->flash = __('Personne Responsable délivrée et client notifié.');
    }

    public function deleteDossier(): mixed
    {
        $this->submission->delete();
        session()->flash('admin_flash', __('Dossier supprimé.'));

        return $this->redirectRoute('admin.submissions.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.submission-detail', [
            'statuses' => SubmissionStatus::cases(),
            'isStarter' => $this->submission->type === SubmissionType::Starter,
        ])->title(__('Dossier').' '.$this->submission->reference.' · Festilaw');
    }
}
