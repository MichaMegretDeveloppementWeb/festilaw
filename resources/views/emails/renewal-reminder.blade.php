<x-mail.layout>
    <x-mail.heading>{{ __('Time to renew your Festilaw :pack', ['pack' => $packLabel]) }}</x-mail.heading>

    <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

    @if ($overdue)
        <x-mail.text>{{ __('Your Festilaw :pack subscription for :year is overdue. To keep your EU Responsible Person active, please renew as soon as possible.', ['pack' => $packLabel, 'year' => $year]) }}</x-mail.text>
    @else
        <x-mail.text>{{ __('It is time to renew your Festilaw :pack for :year. Renew to keep your EU Responsible Person active, without interruption.', ['pack' => $packLabel, 'year' => $year]) }}</x-mail.text>
    @endif

    <x-mail.text>{{ __('The annual fee is :amount. You can pay securely from your file:', ['amount' => $amount]) }}</x-mail.text>

    <x-mail.button :url="$dossierUrl">{{ __('Renew my subscription') }}</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">{!! __('Your reference is :reference. Keep your file link private: anyone who has it can access your file.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>']) !!}</x-mail.text>

    <x-mail.text :muted="true" size="13.5px">{{ __('If you didn\'t start an application with Festilaw, you can safely ignore this email.') }}</x-mail.text>
</x-mail.layout>
