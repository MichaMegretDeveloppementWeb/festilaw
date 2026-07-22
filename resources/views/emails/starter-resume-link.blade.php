<x-mail.layout>
    @if ($isActive)
        <x-mail.heading>{{ __('Your Festilaw :pack', ['pack' => __($submission->type->label())]) }}</x-mail.heading>

        <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

        <x-mail.text>{{ __('Here is your secure link to your Festilaw file. Your :pack is active · you can view it and download your signed mandate and documents:', ['pack' => __($submission->type->label())]) }}</x-mail.text>

        <x-mail.button :url="$resumeUrl">{{ __('Open my file') }}</x-mail.button>

        <x-mail.text :muted="true" size="13.5px">{!! __('Keep this link private: anyone who has it can access your file. Your reference is :reference.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>']) !!}</x-mail.text>
    @else
        <x-mail.heading>{{ __('Continue your Festilaw application') }}</x-mail.heading>

        <x-mail.text>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</x-mail.text>

        <x-mail.text>{{ __('Here is your secure link to continue your Festilaw application (:pack), right where you left off:', ['pack' => __($submission->type->label())]) }}</x-mail.text>

        <x-mail.button :url="$resumeUrl">{{ __('Continue my application') }}</x-mail.button>

        <x-mail.text :muted="true" size="13.5px">{!! __('Keep this link private: anyone who has it can access your file. Your reference is :reference, and the link stays valid for :days days.', ['reference' => '<strong style="color:#0B1E45;">'.e($submission->reference).'</strong>', 'days' => $ttlDays]) !!}</x-mail.text>
    @endif

    <x-mail.text :muted="true" size="13.5px">{{ __('If you didn\'t start an application with Festilaw, you can safely ignore this email.') }}</x-mail.text>
</x-mail.layout>
