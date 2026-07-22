<x-mail.layout>
    <x-mail.heading>{{ __('New contact request') }}</x-mail.heading>
    <x-mail.text :muted="true" size="14px">{{ __('Submitted through the Festilaw website.') }}</x-mail.text>

    <x-mail.panel>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#0B1E45;">
            <tr><td style="padding:5px 0; color:#8a8f9c; width:130px;">{{ __('Name') }}</td><td style="padding:5px 0; font-weight:600;">{{ $submission->first_name }}</td></tr>
            <tr><td style="padding:5px 0; color:#8a8f9c;">{{ __('Email') }}</td><td style="padding:5px 0; font-weight:600;"><a href="mailto:{{ $submission->email }}" style="color:#EC5A57; text-decoration:none;">{{ $submission->email }}</a></td></tr>
            @if ($submission->website_url)
                <tr><td style="padding:5px 0; color:#8a8f9c;">{{ __('Store / website') }}</td><td style="padding:5px 0; font-weight:600;"><a href="{{ $submission->website_url }}" style="color:#EC5A57; text-decoration:none;">{{ $submission->website_url }}</a></td></tr>
            @endif
            <tr><td style="padding:5px 0; color:#8a8f9c;">{{ __('Received') }}</td><td style="padding:5px 0;">{{ $submission->created_at->format('Y-m-d H:i') }}</td></tr>
        </table>
    </x-mail.panel>

    <x-mail.text><strong style="color:#0B1E45;">{{ __('Message') }}</strong></x-mail.text>
    <x-mail.text><span style="white-space:pre-wrap;">{{ $submission->message }}</span></x-mail.text>

    <x-mail.text :muted="true" size="12.5px">{{ __('Reference') }}: {{ $submission->reference }}</x-mail.text>
</x-mail.layout>
