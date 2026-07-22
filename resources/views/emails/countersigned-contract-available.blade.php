<x-mail.layout>
    <x-mail.heading>{{ __('Your countersigned Festilaw contract') }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

    <x-mail.text>{{ __('Good news: your Festilaw contract has been countersigned. The final signed document is attached, and it is also available in your file:') }}</x-mail.text>

    <x-mail.button :url="$dossierUrl">{{ __('Open my file') }}</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">{!! __('Keep this link private: anyone who has it can access your file. Your reference is :reference.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>']) !!}</x-mail.text>
</x-mail.layout>
