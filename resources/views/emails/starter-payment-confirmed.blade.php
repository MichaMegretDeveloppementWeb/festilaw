<p>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</p>

<p>{{ __('Your payment is confirmed and your Festilaw Creator Pack is now active. We\'ll issue your official EU Responsible Person address and email it to you within 24 hours.') }}</p>

<p>{{ __('You can view your file and download your signed mandate and documents any time:') }}</p>

<p><a href="{{ $fileUrl }}">{{ __('Open my file') }}</a></p>

<p>{!! __('Your reference is :reference.', ['reference' => '<strong>'.e($submission->reference).'</strong>']) !!}</p>

<p>{{ __('Thank you for choosing Festilaw.') }}</p>
