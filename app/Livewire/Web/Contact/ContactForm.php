<?php

declare(strict_types=1);

namespace App\Livewire\Web\Contact;

use App\Actions\Web\Contact\CreateContactSubmissionAction;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesUnexpectedErrors;
use App\Livewire\Concerns\HasSpamProtection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class ContactForm extends Component
{
    use HandlesUnexpectedErrors;
    use HasSpamProtection;

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
            'name.required' => __('Please tell us your name.'),
            'email.required' => __('We need your email to reply.'),
            'email.email' => __('This email address looks invalid.'),
            'website_url.url' => __('Please enter a valid URL (including https://).'),
            'message.required' => __('Please write your message.'),
        ];
    }

    public function save(CreateContactSubmissionAction $action): void
    {
        if ($this->looksLikeSpam()) {
            $this->sent = true;

            return;
        }

        if ($this->tooManyAttempts('contact')) {
            return;
        }

        $validated = $this->validate();

        try {
            $action->execute($validated);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('form', __($e->getUserMessage()));

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'form', 'Contact form submit');

            return;
        }

        $this->reset(['name', 'email', 'website_url', 'message']);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.web.contact.contact-form');
    }
}
