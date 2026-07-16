<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\ChangeSubmissionStatusAction;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Back-office : detail d'un dossier (infos client, contrat/signature, pieces, paiements, RDV) et
 * changement manuel de statut. Le modele est charge avec ses relations dans mount (pas de N+1 en vue).
 * Route par id (getRouteKeyName = resume_token cote public, absent pour les dossiers hors STARTER).
 */
#[Layout('layouts.admin')]
class SubmissionDetail extends Component
{
    public Submission $submission;

    public string $newStatus = '';

    public function mount(Submission $submission): void
    {
        $this->submission = $submission->load(['contract', 'uploadedDocuments', 'payments', 'appointment', 'quizResult']);
        $this->newStatus = $this->submission->status->value;
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

        session()->flash('admin_flash', __('Statut mis à jour.'));
    }

    public function render(): View
    {
        return view('livewire.admin.submission-detail', [
            'statuses' => SubmissionStatus::cases(),
        ])->title(__('Dossier').' '.$this->submission->reference.' · Festilaw');
    }
}
