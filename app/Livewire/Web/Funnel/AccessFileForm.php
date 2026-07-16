<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Starter\SendStarterResumeLinkAction;
use App\Livewire\Concerns\HandlesUnexpectedErrors;
use App\Livewire\Concerns\HasSpamProtection;
use App\Services\Starter\StarterDossierFinder;
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
            'email.required' => __('Please enter your email address.'),
            'email.email' => __('This email address looks invalid.'),
        ];
    }

    public function submit(SendStarterResumeLinkAction $sendResumeLink, StarterDossierFinder $finder): void
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
        $dossier = $finder->mostRelevantResumableForEmail($this->email);
        if ($dossier !== null) {
            $sendResumeLink->execute($dossier);
        }

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.web.funnel.access-file-form');
    }
}
