<x-mail.layout>
    <x-mail.heading>{{ __('Your Festilaw Scale audit is confirmed') }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

    @if ($booked)
        {{-- Dossier reserve avant de payer (ordre en vigueur jusqu'en octobre 2026) : le paiement confirme la consultation. --}}
        <x-mail.text>{{ __('Your €75 Scale audit payment is confirmed, and so is your consultation. Our team will confirm the exact slot by email.') }}</x-mail.text>

        <x-mail.button :url="$spaceUrl">{{ __('View my Scale space') }}</x-mail.button>
    @else
        <x-mail.text>{{ __('Your €75 Scale audit payment is confirmed. The next step is to book your video consultation:') }}</x-mail.text>

        <x-mail.button :url="$spaceUrl">{{ __('Book my consultation') }}</x-mail.button>
    @endif

    <x-mail.text>{{ __('Your €75 audit fee will be credited toward your final quote.') }}</x-mail.text>

    <x-mail.text :muted="true" size="13.5px">{!! __('Your reference is :reference.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>']) !!}</x-mail.text>

    <x-mail.text>{{ __('Thank you for choosing Festilaw.') }}</x-mail.text>
</x-mail.layout>
