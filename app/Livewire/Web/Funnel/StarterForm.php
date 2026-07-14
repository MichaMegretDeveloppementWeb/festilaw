<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use Livewire\Component;

class StarterForm extends Component
{
    public string $company_name = '';

    public string $company_registration_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $website_url = '';

    public bool $sent = false;

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
        $data = $this->validate();

        $action->execute([
            'company_name' => $data['company_name'],
            'company_registration_number' => $data['company_registration_number'] ?: null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?: null,
            'email' => $data['email'],
            'website_url' => $data['website_url'] ?: null,
        ]);

        $this->reset([
            'company_name', 'company_registration_number', 'first_name', 'last_name', 'email', 'website_url',
        ]);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.web.funnel.starter-form');
    }
}
