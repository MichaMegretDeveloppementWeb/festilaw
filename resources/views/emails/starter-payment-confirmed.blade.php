<x-mail.layout>
    <x-mail.heading>{{ __('Your Festilaw :pack is active', ['pack' => __($submission->type->label())]) }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

    <x-mail.text>{{ __('Your payment is confirmed and your Festilaw :pack is now active. We\'ll issue your official EU Responsible Person address and email it to you within 24 hours.', ['pack' => __($submission->type->label())]) }}</x-mail.text>

    <x-mail.text>{{ __('You can view your file and download your signed mandate and documents any time:') }}</x-mail.text>

    <x-mail.button :url="$fileUrl">{{ __('Open my file') }}</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">{!! __('Your reference is :reference.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>']) !!}</x-mail.text>

    <x-mail.text>{{ __('Thank you for choosing Festilaw.') }}</x-mail.text>
</x-mail.layout>
