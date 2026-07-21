<p>Bonjour,</p>

@if ($overdue)
    <p>Ces dossiers ont dépassé le délai de paiement de leur renouvellement annuel :</p>
@else
    <p>Ces dossiers doivent renouveler leur abonnement annuel :</p>
@endif

<ul>
    @foreach ($rows as $row)
        <li>
            <strong>{{ $row['company'] }}</strong> · {{ $row['pack'] }} · renouvellement {{ $row['year'] }}
            · {{ $row['email'] }}
            · <a href="{{ $row['url'] }}">voir le dossier</a>
        </li>
    @endforeach
</ul>

<p>Chaque client peut régler son renouvellement depuis son espace dossier. Un rappel lui a été envoyé par email.</p>
