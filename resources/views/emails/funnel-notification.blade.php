<x-mail.layout>
    <x-mail.heading>{{ $reason->subject() }}</x-mail.heading>

    <x-mail.panel>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#0B1E45;">
            <tr><td style="padding:5px 0; color:#8a8f9c; width:110px;">{{ __('Reference') }}</td><td style="padding:5px 0; font-weight:600;">{{ $submission->reference }}</td></tr>
            <tr><td style="padding:5px 0; color:#8a8f9c;">{{ __('Type') }}</td><td style="padding:5px 0;">{{ $submission->type->value }}</td></tr>
            <tr><td style="padding:5px 0; color:#8a8f9c;">{{ __('Status') }}</td><td style="padding:5px 0;">{{ $submission->status->value }}</td></tr>
            <tr><td style="padding:5px 0; color:#8a8f9c;">{{ __('Email') }}</td><td style="padding:5px 0; font-weight:600;">{{ $submission->email }}</td></tr>
            @if ($submission->company_name)
                <tr><td style="padding:5px 0; color:#8a8f9c;">{{ __('Company') }}</td><td style="padding:5px 0; font-weight:600;">{{ $submission->company_name }}</td></tr>
            @endif
        </table>
    </x-mail.panel>
</x-mail.layout>
