<x-mail.layout>
    <x-mail.heading>{{ __('You\'re now on the Festilaw :pack', ['pack' => __($submission->type->label())]) }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

    <x-mail.text>{{ __('Your switch to the :pack is confirmed: we\'ve received your payment of :amount for the rest of :year.', ['pack' => __($submission->type->label()), 'amount' => $amount, 'year' => $year]) }}</x-mail.text>

    <x-mail.text>{{ __('From 1 January, your plan renews at :price per year. Your signed :pack mandate is available in your file:', ['price' => $annualPrice, 'pack' => __($submission->type->label())]) }}</x-mail.text>

    <x-mail.button :url="$fileUrl">{{ __('Open my file') }}</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">{!! __('Your reference is :reference.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>']) !!}</x-mail.text>

    <x-mail.text>{{ __('Thank you for choosing Festilaw.') }}</x-mail.text>
</x-mail.layout>
