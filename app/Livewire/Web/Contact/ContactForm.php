<?php

declare(strict_types=1);

namespace App\Livewire\Web\Contact;

use App\Actions\Web\Contact\CreateContactSubmissionAction;
use Livewire\Component;

class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $website_url = '';

    public string $message = '';

    public bool $sent = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'website_url' => ['nullable', 'url', 'max:200'],
            'message' => ['required', 'string', 'max:3000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'email.required' => 'We need your email to reply.',
            'email.email' => 'This email address looks invalid.',
            'website_url.url' => 'Please enter a valid URL (including https://).',
            'message.required' => 'Please write your message.',
        ];
    }

    public function save(CreateContactSubmissionAction $action): void
    {
        $validated = $this->validate();

        $action->execute($validated);

        $this->reset(['name', 'email', 'website_url', 'message']);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.web.contact.contact-form');
    }
}
