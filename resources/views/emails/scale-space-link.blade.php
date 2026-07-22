<p>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</p>

<p>{{ __('Thanks for requesting a Festilaw Scale audit. Here is your secure link to pay the €75 audit fee and book your 45-minute consultation:') }}</p>

<p><a href="{{ $spaceUrl }}">{{ __('Open my Scale audit') }}</a></p>

<p>{!! __('Keep this link private: anyone who has it can access your file. Your reference is :reference, and the link stays valid for :days days.', ['reference' => '<strong>'.e($submission->reference).'</strong>', 'days' => $ttlDays]) !!}</p>

<p>{{ __('If you didn\'t request a Scale audit with Festilaw, you can safely ignore this email.') }}</p>
