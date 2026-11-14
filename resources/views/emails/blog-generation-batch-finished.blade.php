<x-mail.layout>
    <x-mail.heading>Génération d'articles terminée</x-mail.heading>

    @if ($scheduled)
        <x-mail.text>La génération automatique vient de se terminer. Voici ce qu'elle a produit :</x-mail.text>
    @else
        <x-mail.text>La génération lancée depuis le back-office vient de se terminer :</x-mail.text>
    @endif

    @foreach ($rows as $row)
        <x-mail.panel>
            <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                <strong style="color:#0B1E45;">{{ $row['subject'] }}</strong><br>
                <span style="color:{{ $row['succeeded'] ? '#1c7a4a' : '#b4532a' }};">{{ $row['outcome'] }}</span>
                <span style="color:#8a8f9c;"> · {{ $row['cost'] }}</span>
            </div>
        </x-mail.panel>
    @endforeach

    <x-mail.text>Coût total : <strong>{{ $totalCost }}</strong>.</x-mail.text>

    <x-mail.button :url="$url">Ouvrir les articles</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">Un article généré reste un brouillon : rien n'est publié sans votre validation.</x-mail.text>
</x-mail.layout>
