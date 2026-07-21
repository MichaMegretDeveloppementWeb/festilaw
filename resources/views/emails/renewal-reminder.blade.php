<p>{{ __('Hello') }}{{ $submission->first_name ? ' '.$submission->first_name : '' }},</p>

@if ($overdue)
    <p>{{ __('Your Festilaw :pack subscription for :year is overdue. To keep your EU Responsible Person active, please renew as soon as possible.', ['pack' => $packLabel, 'year' => $year]) }}</p>
@else
    <p>{{ __('It is time to renew your Festilaw :pack for :year. Renew to keep your EU Responsible Person active, without interruption.', ['pack' => $packLabel, 'year' => $year]) }}</p>
@endif

<p>{{ __('The annual fee is :amount. You can pay securely from your file:', ['amount' => $amount]) }}</p>

<p><a href="{{ $dossierUrl }}">{{ __('Renew my subscription') }}</a></p>

<p>{!! __('Your reference is :reference. Keep your file link private: anyone who has it can access your file.', ['reference' => '<strong>'.e($submission->reference).'</strong>']) !!}</p>

<p>{{ __('If you didn\'t start an application with Festilaw, you can safely ignore this email.') }}</p>
