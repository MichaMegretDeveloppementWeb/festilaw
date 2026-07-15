<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel\Concerns;

/**
 * Champs et validation partages par les parcours PRO et SCALE (memes champs de contact).
 */
trait HasFunnelContactFields
{
    public string $company_name = '';

    public string $email = '';

    public string $first_name = '';

    public string $website_url = '';

    public string $product_types = '';

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
            'company_name.required' => __('Please tell us your company name.'),
            'email.required' => __('We need your email to continue.'),
            'email.email' => __('This email address looks invalid.'),
            'website_url.url' => __('Please enter a valid URL (including https://).'),
        ];
    }

    /** @return array<string, string|null> */
    protected function funnelData(): array
    {
        return [
            'company_name' => $this->company_name,
            'email' => $this->email,
            'first_name' => $this->first_name ?: null,
            'website_url' => $this->website_url ?: null,
            'product_types' => $this->product_types ?: null,
        ];
    }

    protected function resetContactFields(): void
    {
        $this->reset(['company_name', 'email', 'first_name', 'website_url', 'product_types']);
    }
}
