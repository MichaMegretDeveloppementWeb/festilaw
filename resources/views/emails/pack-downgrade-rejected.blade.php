<x-mail.layout>
    <x-mail.heading>{{ __('About your request to switch to the Festilaw :pack', ['pack' => $pack]) }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

    <x-mail.text>{{ __('We\'re unable to switch your file to the :pack for now: your :current continues as before.', ['pack' => $pack, 'current' => $currentPack]) }}</x-mail.text>

    @if ($note)
        <x-mail.text>{{ $note }}</x-mail.text>
    @endif

    <x-mail.text>{{ __('If you have any questions, contact us at :email.', ['email' => 'team@festilaw.com']) }}</x-mail.text>

    <x-mail.button :url="$fileUrl">{{ __('Open my file') }}</x-mail.button>

    <x-mail.text>{{ __('Thank you for choosing Festilaw.') }}</x-mail.text>
</x-mail.layout>
