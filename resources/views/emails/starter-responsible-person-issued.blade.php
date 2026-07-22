<x-mail.layout>
    <x-mail.heading>{{ __('Your EU Responsible Person is now live') }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $firstName ? ' '.$firstName : '' }},</x-mail.text>

    <x-mail.text>{{ __('Great news: your EU Responsible Person is now live. You can display the following official EU contact details on your products and listings:') }}</x-mail.text>

    <x-mail.panel tone="accent"><div style="white-space:pre-wrap; font-weight:700; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#0B1E45;">{{ $address }}</div></x-mail.panel>

    <x-mail.text>{{ __('Everything is available in your project space:') }}</x-mail.text>

    <x-mail.button :url="$fileUrl">{{ __('View my project') }}</x-mail.button>

    <x-mail.text>{{ __('Thank you for trusting Festilaw.') }}</x-mail.text>
</x-mail.layout>
