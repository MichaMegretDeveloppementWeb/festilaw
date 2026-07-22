<p>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</p>

<p>{{ __('Your €75 Scale audit payment is confirmed. The next step is to book your 45-minute video consultation:') }}</p>

<p><a href="{{ $spaceUrl }}">{{ __('Book my consultation') }}</a></p>

<p>{{ __('Your €75 audit fee will be credited toward your final quote.') }}</p>

<p>{!! __('Your reference is :reference.', ['reference' => '<strong>'.e($submission->reference).'</strong>']) !!}</p>

<p>{{ __('Thank you for choosing Festilaw.') }}</p>
