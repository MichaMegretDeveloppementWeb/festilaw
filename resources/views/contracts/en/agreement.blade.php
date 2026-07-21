<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @include('contracts._styles')
</head>
<body>
    <table class="doc-head"><tr>
        @if ($logo)<td width="46"><img class="brand-logo" src="{{ $logo }}" alt=""></td>@endif
        <td><span class="eyebrow">Festilaw &middot; European Product Safety Compliance Services</span></td>
    </tr></table>
    <div class="head-rule"></div>
    <h1>Responsible Person Service Agreement</h1>
    <div class="pack">Pack {{ $pack }}</div>
    <div class="meta">Reference: {{ $reference }} &middot; Date: {{ $date }}</div>

    <h2>1. Parties</h2>
    <p><strong>Service Provider:</strong> Festilaw B.V., a company incorporated under Dutch law, acting as the "Responsible Person" within the meaning of EU product safety regulations.</p>
    <p><strong>Client:</strong> {!! $company !!}, a company founded in {!! $place !!} in {!! $year !!} and specialising in {!! $activity !!}.</p>
    <p>Together referred to as "Parties". This Agreement is effective as of the date of last signature below.</p>

    <h2>2. Appointment and Scope of Services</h2>
    <p><strong>Responsible Person Appointment:</strong> {!! $company !!} appoints Festilaw as its European Union Responsible Person for consumer products sold in the EU, as required by the EU General Product Safety Regulation (EU) 2023/988 (GPSR). Festilaw accepts this appointment. This agreement serves as the written mandate for Festilaw to act as {!! $company !!}'s authorized representative in the EU, in accordance with Article 4 of Regulation (EU) 2019/1020 (for harmonized products) and the GPSR for non-harmonized consumer products.</p>

    <h2>3. Fees, Payment, and Additional Services</h2>
    <p><strong>3.1</strong>&nbsp;&nbsp;Annual Fee: {!! $company !!} shall pay an annual fee of EUR {{ $fee }} ({{ $feeWords }}) for the Responsible Person services, in accordance with the following terms:</p>
    <p>(a) Service Year. For the purposes of this Agreement, a service year runs from 1 January to 31 December of each calendar year. (b) First Year, Pro Rata. For the calendar year in which this Agreement is signed, the annual fee shall be calculated on a pro rata basis from the date of signature to 31 December of that year. The pro rata amount shall be invoiced by Festilaw upon signature of this Agreement and is due and payable within thirty (30) days of receipt of invoice. (c) Second Year and Subsequent Years. From 1 January of the year following signature, the full annual fee of EUR {{ $fee }} shall apply for each calendar year. Festilaw shall issue an invoice in January of each service year. Payment is due within thirty (30) days of receipt of invoice. (d) Scope. The annual fee covers all the essentials of the Responsible Person role as described in the General Terms section 2.2. (e) Document Retention. Festilaw shall retain all technical documentation and compliance records relating to {!! $company !!}'s products for a period of ten (10) years following the termination or expiry of this Agreement, in accordance with applicable EU product safety regulations and Festilaw's obligations as EU Responsible Person.</p>
    <p><strong>3.2</strong>&nbsp;&nbsp;Additional services: Any additional services will be invoiced in accordance with the General Terms, Section 3.2.</p>

    <h2>4. EU Responsible Person Address &amp; Display Obligations</h2>
    <p><strong>4.1</strong>&nbsp;&nbsp;Communication of Address. Festilaw's EU Responsible Person address and contact details ("the RP Address") shall be communicated to {!! $company !!} in writing only after Festilaw has declared the initial compliance review complete. Prior to this communication, Festilaw reserves the right to request any additional documents, certificates, or technical files deemed necessary to verify how {!! $company !!} has complied with applicable EU regulations, including but not limited to the EU Declaration of Conformity (DoC), risk assessments, or safety test reports. {!! $company !!} undertakes to promptly supply all such requested documentation. Prior to Festilaw's written confirmation that this review is complete, the Client shall not display, publish, or communicate Festilaw's details in any form, on any medium.</p>
    <p><strong>4.2</strong>&nbsp;&nbsp;Display Obligation. {!! $company !!} acknowledges and confirms that it has understood that, pursuant to Article 9(7) of EU Regulation 2023/988 (GPSR), the EU Responsible Person's name, registered trade name or registered trade mark, postal address, and electronic address must appear on the product or its packaging, parcel, or an accompanying document. {!! $company !!} undertakes to ensure that the RP Address is displayed on the external packaging of all products sold or made available on the EU market, in a manner that is clearly legible and immediately accessible to customs authorities and market surveillance authorities without the need to open the product packaging.</p>
    <p><strong>4.3</strong>&nbsp;&nbsp;Wording. Upon communication of the RP Address, Festilaw shall also provide {!! $company !!} with the exact Responsible Person wording to be used on packaging, product manuals, website, and product listing pages, in the form required by the GPSR. {!! $company !!} undertakes to use this wording without modification.</p>
    <p><strong>4.4</strong>&nbsp;&nbsp;Small Product Exception. Where the size or nature of a product makes it physically impossible or impractical to display the RP Address on the external packaging in the manner described in Article 4.2 (for example, very small products or products without individual outer packaging), the applicable display alternative shall be determined jointly by Festilaw and {!! $company !!}, prior to placing those products on the EU market, in accordance with the derogations and alternative display methods permitted under the GPSR. {!! $company !!} shall not unilaterally decide to omit the RP Address without Festilaw's prior written agreement on the applicable alternative.</p>

    <h2>5. Acceptance of the General Terms</h2>
    <p><strong>5.1</strong>&nbsp;&nbsp;{!! $company !!} has read and acknowledged the general terms as attached to this contract.</p>
    <p><strong>5.2</strong>&nbsp;&nbsp;Any amendments to this Agreement must be made in writing and signed by both Parties, in accordance with the General Terms section 8.2.</p>

    <h2>6. Signing</h2>
    <p>By signing below, each Party confirms its agreement to the terms of this Responsible Person Service Agreement.</p>
    <div class="sign-zone">
        <div class="sign-party">
            <p><span class="sign-name">Festilaw B.V.</span> <span class="muted">(Responsible Person Service Provider)</span></p>
            <p class="sign-row">Name: ______________________</p>
            <table class="sign-fields"><tr>
                <td>Signature: ______________________</td>
                <td>Date: ______________________</td>
            </tr></table>
        </div>
        <div class="sign-party">
            <p><span class="sign-name">{!! $company !!}</span> <span class="muted">(Client)</span></p>
            <p class="sign-row">Name: {{ $signer }}</p>
            <table class="sign-fields"><tr>
                <td>Signature: <span class="sign-tag">@{{signature:1:y}}</span></td>
                <td>Date: <span class="sign-tag">@{{date:1:y}}</span></td>
            </tr></table>
        </div>
    </div>
    <p class="muted">The Client's signature is captured electronically via Festilaw's signing partner, with a tamper-evident audit trail. Festilaw counter-signs the mandate to complete it.</p>

    <div class="page-break"></div>
    @include('contracts.en.general-terms')
</body>
</html>
