<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #0B1E45; background: #FBEEDC; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 2px solid #0B1E45; border-radius: 16px; padding: 28px 30px;">
        <h2 style="margin: 0 0 6px; font-size: 20px;">New contact request</h2>
        <p style="margin: 0 0 20px; color: #5C5344; font-size: 14px;">Submitted through the Festilaw website.</p>

        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr>
                <td style="padding: 6px 0; color: #5C5344; width: 130px;">Name</td>
                <td style="padding: 6px 0; font-weight: 600;">{{ $submission->first_name }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #5C5344;">Email</td>
                <td style="padding: 6px 0; font-weight: 600;"><a href="mailto:{{ $submission->email }}" style="color: #EC5A57;">{{ $submission->email }}</a></td>
            </tr>
            @if ($submission->website_url)
                <tr>
                    <td style="padding: 6px 0; color: #5C5344;">Store / website</td>
                    <td style="padding: 6px 0; font-weight: 600;"><a href="{{ $submission->website_url }}" style="color: #EC5A57;">{{ $submission->website_url }}</a></td>
                </tr>
            @endif
            <tr>
                <td style="padding: 6px 0; color: #5C5344;">Received</td>
                <td style="padding: 6px 0;">{{ $submission->created_at->format('Y-m-d H:i') }}</td>
            </tr>
        </table>

        <p style="margin: 20px 0 6px; font-weight: 600;">Message</p>
        <p style="margin: 0; white-space: pre-wrap; line-height: 1.55;">{{ $submission->message }}</p>

        <hr style="border: none; border-top: 1px solid #E7D9BF; margin: 24px 0 12px;">
        <p style="margin: 0; color: #a49a86; font-size: 12px;">Reference: {{ $submission->reference }}</p>
    </div>
</body>
</html>
