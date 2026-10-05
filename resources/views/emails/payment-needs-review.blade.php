<x-mail.layout>
    <x-mail.heading>Paiement à vérifier</x-mail.heading>

    <x-mail.text>Un paiement vient d'être confirmé par Stripe sur le dossier <strong>{{ $payment->submission?->reference }}</strong>{{ $payment->submission?->company_name ? ' ('.$payment->submission->company_name.')' : '' }}, mais il demande une vérification.</x-mail.text>

    <x-mail.panel>
        <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
            <strong style="color:#0B1E45;">{{ $payment->type->label() }}{{ $payment->service_year ? ' · '.$payment->service_year : '' }}</strong> · {{ $amount }}<br>
            <span style="color:#8a8f9c;">Référence Stripe : {{ $payment->provider_reference }}</span>
        </div>
    </x-mail.panel>

    @if ($review->isDuplicate())
        <x-mail.text><strong>Paiement en double.</strong> Cette même prestation était déjà réglée par le paiement du {{ $review->duplicateOf->paid_at?->format('d/m/Y') ?? '—' }} ({{ $duplicateAmount }}, référence {{ $review->duplicateOf->provider_reference }}). Le client a donc payé deux fois : remboursez l'un des deux depuis Stripe, le site enregistrera le remboursement. Le client n'a pas reçu de second e-mail de confirmation.</x-mail.text>
    @endif

    @if ($review->amountMismatch())
        <x-mail.text><strong>Montant différent.</strong> Stripe a encaissé {{ $chargedAmount }} alors que le site attendait {{ $expectedAmount }}. Vérifiez ce paiement dans Stripe.</x-mail.text>
    @endif

    <x-mail.button :url="$dossierUrl">Voir le dossier</x-mail.button>
</x-mail.layout>
