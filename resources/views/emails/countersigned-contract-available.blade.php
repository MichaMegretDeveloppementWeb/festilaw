<p>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</p>

<p>{{ __('Good news: your Festilaw contract has been countersigned. The final signed document is attached, and it is also available in your file:') }}</p>

<p><a href="{{ $dossierUrl }}">{{ __('Open my file') }}</a></p>

<p>{!! __('Keep this link private: anyone who has it can access your file. Your reference is :reference.', ['reference' => '<strong>'.e($submission->reference).'</strong>']) !!}</p>
