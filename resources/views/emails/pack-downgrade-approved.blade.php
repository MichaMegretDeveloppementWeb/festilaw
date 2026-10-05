<x-mail.layout>
    <x-mail.heading>{{ __('Your switch to the Festilaw :pack is confirmed', ['pack' => $pack]) }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

    <x-mail.text>{{ __('We\'ve approved your request: your file switches to the :pack from 1 January :year. Until then, nothing changes for you.', ['pack' => $pack, 'year' => $year]) }}</x-mail.text>

    <x-mail.text>{{ __('At your renewal, you\'ll sign your :pack mandate in your file, then renew at :price per year.', ['pack' => $pack, 'price' => $price]) }}</x-mail.text>

    <x-mail.button :url="$fileUrl">{{ __('Open my file') }}</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">{!! __('Your reference is :reference.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>']) !!}</x-mail.text>

    <x-mail.text>{{ __('Thank you for choosing Festilaw.') }}</x-mail.text>
</x-mail.layout>
