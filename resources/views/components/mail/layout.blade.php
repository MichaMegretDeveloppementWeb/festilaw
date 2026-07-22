@props(['preheader' => null])
{{-- Festilaw branded email shell. Table-based, all CSS inline (Gmail/Outlook strip <style>). Sober:
     navy ink, coral CTA, warm-neutral page, white card, hosted logo (alt text styled so it stays
     legible if images are blocked). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Festilaw</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f3f1ec; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    @if ($preheader)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">{{ $preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f1ec;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:640px; max-width:640px;">
                    <tr>
                        <td style="padding:2px 6px 18px;">
                            <a href="{{ config('app.url') }}" style="text-decoration:none;">
                                <img src="{{ asset('logo-festilaw.jpg') }}" width="130" alt="Festilaw" style="display:block; width:130px; max-width:130px; height:auto; border:0; outline:none; color:#0B1E45; font-family:Georgia,'Times New Roman',serif; font-size:22px; font-weight:700; letter-spacing:0.3px;">
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff; border:1px solid #ece7de; border-radius:16px; padding:36px 40px;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 20px 6px; text-align:center;">
                            <p style="margin:0 0 4px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.5; color:#9a9ba5;">Festilaw · {{ __('Your GPSR Responsible Person in the EU') }}</p>
                            <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.5; color:#b6b7bf;">© {{ date('Y') }} Festilaw. {{ __('All rights reserved.') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
