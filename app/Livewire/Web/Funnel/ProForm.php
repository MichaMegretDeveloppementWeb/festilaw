<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Pro\CreateProSubmissionAction;
use Livewire\Component;

class ProForm extends Component
{
    public string $company_name = '';

    public string $email = '';

    public string $first_name = '';

    public string $website_url = '';

    public string $product_types = '';

    public bool $sent = false;

    /** @return array<string, array<int, string>> */
    protected function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'website_url' => ['nullable', 'url', 'max:200'],
            'product_types' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'company_name.required' => 'Please tell us your company name.',
            'email.required' => 'We need your email to reply.',
            'email.email' => 'This email address looks invalid.',
            'website_url.url' => 'Please enter a valid URL (including https://).',
        ];
    }

    public function submit(CreateProSubmissionAction $action): mixed
    {
        $data = $this->validate();

        $action->execute([
            'company_name' => $data['company_name'],
            'email' => $data['email'],
            'first_name' => $data['first_name'] ?: null,
            'website_url' => $data['website_url'] ?: null,
            'product_types' => $data['product_types'] ?: null,
        ]);

        // WhatsApp fourni par la cliente (LV2). En attendant, on affiche un etat de succes.
        $whatsapp = config('festilaw.pro.whatsapp_url');
        if (is_string($whatsapp) && $whatsapp !== '') {
            return $this->redirect($whatsapp);
        }

        $this->reset(['company_name', 'email', 'first_name', 'website_url', 'product_types']);
        $this->sent = true;

        return null;
    }

    public function render()
    {
        return view('livewire.web.funnel.pro-form');
    }
}
