<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Starter\SendStarterResumeLinkAction;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Concerns\HandlesUnexpectedErrors;
use App\Livewire\Concerns\HasSpamProtection;
use App\Models\Submission;
use Livewire\Component;

/**
 * "Access my file": the customer enters their email and gets their secure magic link by email · the
 * no-account way back into their dossier. For privacy, the response is always the same generic message,
 * so it never reveals whether an email has a file. Sends the link only when one actually exists.
 */
class AccessFileForm extends Component
{
    use HandlesUnexpectedErrors;
    use HasSpamProtection;

    public string $email = '';

    public bool $sent = false;

    /** @return array<string, array<int, string>> */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:180'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'This email address looks invalid.',
        ];
    }

    public function submit(SendStarterResumeLinkAction $sendResumeLink): void
    {
        if ($this->looksLikeSpam()) {
            $this->sent = true;

            return;
        }

        if ($this->tooManyAttempts('access-file')) {
            return;
        }

        $this->validate();

        // On envoie le lien seulement si un dossier existe, mais le message est toujours le meme
        // (on ne revele jamais si un email a un dossier). L'envoi est resilient (ne casse rien).
        $dossier = $this->findDossier($this->email);
        if ($dossier !== null) {
            $sendResumeLink->execute($dossier);
        }

        $this->sent = true;
    }

    /** The most relevant still-resumable STARTER dossier for this email: active first, else most advanced. */
    private function findDossier(string $email): ?Submission
    {
        return Submission::query()
            ->where('type', SubmissionType::Starter)
            ->where('email', $email)
            ->whereIn('status', [
                SubmissionStatus::Paid,
                SubmissionStatus::Completed,
                SubmissionStatus::AwaitingPayment,
                SubmissionStatus::AwaitingDocuments,
                SubmissionStatus::InProgress,
            ])
            ->resumable()
            ->orderByRaw("CASE status WHEN 'paid' THEN 0 WHEN 'completed' THEN 1 WHEN 'awaiting_payment' THEN 2 WHEN 'awaiting_documents' THEN 3 ELSE 4 END")
            ->latest()
            ->first();
    }

    public function render()
    {
        return view('livewire.web.funnel.access-file-form');
    }
}
