<x-mail.layout>
    <x-mail.heading>{{ __('Your Festilaw Scale audit') }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

    <x-mail.text>{{ __('Thanks for requesting a Festilaw Scale audit. Here is your secure link to pay the €75 audit fee and book your 45-minute consultation:') }}</x-mail.text>

    <x-mail.button :url="$spaceUrl">{{ __('Open my Scale audit') }}</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">{!! __('Keep this link private: anyone who has it can access your file. Your reference is :reference, and the link stays valid for :days days.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>', 'days' => $ttlDays]) !!}</x-mail.text>

    <x-mail.text :muted="true" size="13.5px">{{ __('If you didn\'t request a Scale audit with Festilaw, you can safely ignore this email.') }}</x-mail.text>
</x-mail.layout>
