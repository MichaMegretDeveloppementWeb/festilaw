<x-mail.layout>
    <x-mail.heading>{{ __('Your Festilaw Scale audit is confirmed') }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

    <x-mail.text>{{ __('Your €75 Scale audit payment is confirmed. The next step is to book your video consultation:') }}</x-mail.text>

    <x-mail.button :url="$spaceUrl">{{ __('Book my consultation') }}</x-mail.button>

    <x-mail.text>{{ __('Your €75 audit fee will be credited toward your final quote.') }}</x-mail.text>

    <x-mail.text :muted="true" size="13.5px">{!! __('Your reference is :reference.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>']) !!}</x-mail.text>

    <x-mail.text>{{ __('Thank you for choosing Festilaw.') }}</x-mail.text>
</x-mail.layout>
