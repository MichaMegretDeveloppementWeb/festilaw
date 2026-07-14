<p>{{ $reason->subject() }}</p>

<ul>
    <li>Reference: {{ $submission->reference }}</li>
    <li>Type: {{ $submission->type->value }}</li>
    <li>Status: {{ $submission->status->value }}</li>
    <li>Email: {{ $submission->email }}</li>
    @if ($submission->company_name)
        <li>Company: {{ $submission->company_name }}</li>
    @endif
</ul>
