<p>{{ __('Hello') }}{{ $firstName ? ' '.$firstName : '' }},</p>

<p>{{ __('Great news: your EU Responsible Person is now live. You can display the following official EU contact details on your products and listings:') }}</p>

<p style="padding: 12px 16px; background: #f4f5fb; border-radius: 8px; white-space: pre-wrap;"><strong>{{ $address }}</strong></p>

<p>{{ __('Everything is available in your project space:') }} <a href="{{ $fileUrl }}">{{ __('View my project') }}</a></p>

<p>{{ __('Thank you for trusting Festilaw.') }}</p>
