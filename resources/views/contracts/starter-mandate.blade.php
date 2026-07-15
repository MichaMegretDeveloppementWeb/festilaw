<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 90px 64px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.55; }
        h1 { font-size: 19px; color: #4b4ddb; margin: 0 0 4px; }
        .eyebrow { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: #FE776A; font-weight: bold; }
        .meta { color: #555; font-size: 11px; margin-bottom: 22px; }
        h2 { font-size: 13px; color: #4b4ddb; margin: 20px 0 6px; }
        table.party { width: 100%; border-collapse: collapse; margin: 10px 0 4px; }
        table.party td { padding: 3px 0; vertical-align: top; }
        table.party td.label { width: 190px; color: #555; }
        .clause { margin: 8px 0; }
        .sign-zone { margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px; }
        .sign-line { margin-top: 14px; }
        .sign-tag { color: #4b4ddb; }
        .muted { color: #777; font-size: 10.5px; }
    </style>
</head>
<body>
    <div class="eyebrow">{{ __('Festilaw · GPSR Responsible Person') }}</div>
    <h1>{{ __('Mandate · EU Responsible Person (GPSR)') }}</h1>
    <div class="meta">{{ __('Reference') }}: {{ $submission->reference }} · {{ __('Date') }}: {{ $date }}</div>

    <p class="clause">
        {!! __('This mandate is entered into between :festilaw (the "Responsible Person") and the undersigned economic operator (the "Manufacturer"), for the purposes of Regulation (EU) 2023/988 on general product safety (GPSR).', ['festilaw' => '<strong>Festilaw</strong>']) !!}
    </p>

    <h2>{{ __('The Manufacturer') }}</h2>
    <table class="party">
        <tr><td class="label">{{ __('Company') }}</td><td><strong>{{ $submission->company_name ?: '-' }}</strong></td></tr>
        <tr><td class="label">{{ __('Registration number') }}</td><td>{{ $submission->company_registration_number ?: '-' }}</td></tr>
        <tr><td class="label">{{ __('Represented by') }}</td><td>{{ trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: '-' }}</td></tr>
        <tr><td class="label">{{ __('Email') }}</td><td>{{ $submission->email }}</td></tr>
        <tr><td class="label">{{ __('Store / website') }}</td><td>{{ $submission->website_url ?: '-' }}</td></tr>
    </table>

    <h2>{{ __('1. Purpose') }}</h2>
    <p class="clause">
        {{ __('The Manufacturer appoints Festilaw as its EU Responsible Person under Article 16 GPSR. Festilaw agrees to act as the point of contact within the European Union for the products placed on the market by the Manufacturer.') }}
    </p>

    <h2>{{ __('2. Duties of the Responsible Person') }}</h2>
    <p class="clause">
        {{ __('Festilaw shall keep the declaration of conformity and technical documentation at the disposal of market surveillance authorities, cooperate with those authorities, and inform them of any product presenting a risk.') }}
    </p>

    <h2>{{ __('3. Duties of the Manufacturer') }}</h2>
    <p class="clause">
        {{ __('The Manufacturer shall provide accurate product information and documentation, keep it up to date, and display Festilaw\'s contact details on the products or their packaging as required by the GPSR.') }}
    </p>

    <h2>{{ __('4. Term') }}</h2>
    <p class="clause">
        {{ __('This mandate takes effect on the date of signature and remains valid for the subscription period, renewable by agreement between the parties.') }}
    </p>

    <div class="sign-zone">
        <p><strong>{{ __('Signed by the Manufacturer') }}</strong></p>
        <table class="party">
            <tr><td class="label">{{ __('Name') }}</td><td>{{ trim(($submission->first_name ?? '').' '.($submission->last_name ?? '')) ?: $submission->company_name }}</td></tr>
            <tr><td class="label">{{ __('Date') }}</td><td>{{ $date }}</td></tr>
        </table>
        <p class="sign-line muted">{{ __('The Manufacturer\'s electronic signature is captured on the signature page that follows, then bound to this document.') }}</p>
        <p class="muted">{{ __('Electronic signature via Festilaw\'s signing partner. This document is sealed on completion; any alteration invalidates the seal.') }}</p>
    </div>
</body>
</html>
