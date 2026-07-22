@props(['tone' => 'neutral'])
{{-- Highlight box (reference, RP address, key details). Tones: neutral / accent (light indigo). --}}
@php
    $bg = $tone === 'accent' ? '#f6f5fb' : '#f7f5f0';
    $border = $tone === 'accent' ? '#e5e4f4' : '#ece7de';
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:2px 0 20px;">
    <tr>
        <td style="background-color:{{ $bg }}; border:1px solid {{ $border }}; border-radius:10px; padding:14px 18px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14.5px; line-height:1.6; color:#0B1E45;">{{ $slot }}</td>
    </tr>
</table>
