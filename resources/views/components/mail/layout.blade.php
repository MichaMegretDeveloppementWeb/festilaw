@props(['preheader' => null])
{{-- Festilaw branded email shell. Table-based, all CSS inline (Gmail/Outlook strip <style>). Sober:
     navy ink, coral CTA, white page, white card drawn by a thin rounded border, hosted logo (alt text
     styled so it stays legible if images are blocked). Fond blanc et marges serrees sur telephone (demande de
     Micha du 24/11/2026) : la marge de la messagerie, la notre et celle de la carte mangeaient pres de la
     moitie de l'ecran. Le seul <style> est la requete media du telephone, que Gmail, Apple Mail et Outlook
     sur mobile appliquent ; une messagerie qui l'ignore garde les marges d'ordinateur. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Festilaw</title>
    <style>
        @media only screen and (max-width: 600px) {
            .fl-container { width: 100% !important; max-width: 100% !important; }
            .fl-page { padding: 8px !important; }
            .fl-card { padding: 20px 8px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#ffffff; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    @if ($preheader)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">{{ $preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;">
        <tr>
            <td align="center" class="fl-page" style="padding:32px 16px;">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" class="fl-container" style="width:640px; max-width:640px;">
                    <tr>
                        <td align="center" style="padding:2px 6px 20px; text-align:center;">
                            <a href="{{ localized_route('home') }}" style="text-decoration:none;">
                                <img src="{{ asset('logo-festilaw.png') }}" width="104" alt="Festilaw" style="display:block; margin:0 auto; width:104px; max-width:104px; height:auto; border:0; outline:none; color:#0B1E45; font-family:Georgia,'Times New Roman',serif; font-size:22px; font-weight:700; letter-spacing:0.3px;">
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td class="fl-card" style="background-color:#ffffff; border:1px solid #e6e8ee; border-radius:16px; padding:36px 40px;">
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
