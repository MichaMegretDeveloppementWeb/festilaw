<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesUnexpectedErrors;
use App\Livewire\Concerns\HasSpamProtection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class StarterForm extends Component
{
    use HandlesUnexpectedErrors;
    use HasSpamProtection;

    public string $company_name = '';

    public string $company_registration_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $website_url = '';

    public bool $sent = false;

    /** True when the email already had an open dossier : we re-sent the resume link instead. */
    public bool $resent = false;

    /** @return array<string, array<int, string>> */
    protected function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:180'],
            'company_registration_number' => ['nullable', 'string', 'max:60'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'website_url' => ['nullable', 'url', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'company_name.required' => 'Please tell us your company name.',
            'first_name.required' => 'Please tell us your first name.',
            'email.required' => 'We need your email to continue.',
            'email.email' => 'This email address looks invalid.',
            'website_url.url' => 'Please enter a valid URL (including https://).',
        ];
    }

    public function submit(CreateStarterSubmissionAction $action): void
    {
        if ($this->looksLikeSpam()) {
            $this->sent = true;

            return;
        }

        if ($this->tooManyAttempts('funnel-starter')) {
            return;
        }

        $this->validate();

        try {
            $outcome = $action->execute([
                'company_name' => $this->company_name,
                'company_registration_number' => $this->company_registration_number ?: null,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name ?: null,
                'email' => $this->email,
                'website_url' => $this->website_url ?: null,
            ]);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('form', $e->getUserMessage());

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'form', 'STARTER file open submit');

            return;
        }

        // Un dossier existait deja pour cet email : on a renvoye le lien de reprise par email. On ne
        // redirige pas dans le dossier (le token vaut acces : un simple email ne doit pas y donner acces).
        if (! $outcome->isNew) {
            $this->resent = true;

            return;
        }

        // Nouveau dossier : on enchaine directement sur le parcours (signer -> televerser -> payer).
        $this->redirectRoute('get-started.starter.journey', [
            'locale' => app()->getLocale(),
            'dossier' => $outcome->submission->resume_token,
        ], navigate: true);
    }

    public function render()
    {
        return view('livewire.web.funnel.starter-form');
    }
}
