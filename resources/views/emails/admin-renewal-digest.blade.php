<x-mail.layout>
    <x-mail.heading>{{ $overdue ? 'Renouvellements en retard' : 'Renouvellements à venir' }}</x-mail.heading>

    @if ($overdue)
        <x-mail.text>Ces dossiers ont dépassé le délai de paiement de leur renouvellement annuel :</x-mail.text>
    @else
        <x-mail.text>Ces dossiers doivent renouveler leur abonnement annuel :</x-mail.text>
    @endif

    @foreach ($rows as $row)
        <x-mail.panel>
            <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                <strong style="color:#0B1E45;">{{ $row['company'] }}</strong> · {{ $row['pack'] }} · renouvellement {{ $row['year'] }}<br>
                <span style="color:#8a8f9c;">{{ $row['email'] }}</span> · <a href="{{ $row['url'] }}" style="color:#EC5A57; text-decoration:none; font-weight:600;">voir le dossier</a>
            </div>
        </x-mail.panel>
    @endforeach

    <x-mail.text :muted="true" size="13.5px">Chaque client peut régler son renouvellement depuis son espace dossier. Un rappel lui a été envoyé par email.</x-mail.text>
</x-mail.layout>
