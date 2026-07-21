<p>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</p>

@if ($isActive)
    <p>{{ __('Here is your secure link to your Festilaw file. Your :pack is active · you can view it and download your signed mandate and documents:', ['pack' => __($submission->type->label())]) }}</p>

    <p><a href="{{ $resumeUrl }}">{{ __('Open my file') }}</a></p>

    <p>{!! __('Keep this link private: anyone who has it can access your file. Your reference is :reference.', ['reference' => '<strong>'.e($submission->reference).'</strong>']) !!}</p>
@else
    <p>{{ __('Here is your secure link to continue your Festilaw application (:pack), right where you left off:', ['pack' => __($submission->type->label())]) }}</p>

    <p><a href="{{ $resumeUrl }}">{{ __('Continue my application') }}</a></p>

    <p>{!! __('Keep this link private: anyone who has it can access your file. Your reference is :reference, and the link stays valid for :days days.', ['reference' => '<strong>'.e($submission->reference).'</strong>', 'days' => $ttlDays]) !!}</p>
@endif

<p>{{ __('If you didn\'t start an application with Festilaw, you can safely ignore this email.') }}</p>
