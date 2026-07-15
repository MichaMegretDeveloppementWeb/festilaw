<p>{{ $reason->subject() }}</p>

<ul>
    <li>{{ __('Reference') }}: {{ $submission->reference }}</li>
    <li>{{ __('Type') }}: {{ $submission->type->value }}</li>
    <li>{{ __('Status') }}: {{ $submission->status->value }}</li>
    <li>{{ __('Email') }}: {{ $submission->email }}</li>
    @if ($submission->company_name)
        <li>{{ __('Company') }}: {{ $submission->company_name }}</li>
    @endif
</ul>
