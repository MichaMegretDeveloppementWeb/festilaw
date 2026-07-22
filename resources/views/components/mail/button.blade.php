@props(['url'])
{{-- CTA table-based so Outlook renders the padding. --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 22px;">
    <tr>
        <td align="center" style="border-radius:10px; background-color:#EC5A57;">
            <a href="{{ $url }}" style="display:inline-block; padding:13px 28px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; font-weight:600; line-height:1; color:#ffffff; text-decoration:none; border-radius:10px;">{{ $slot }}</a>
        </td>
    </tr>
</table>
