<x-mail.layout>
    <x-mail.heading>Générations d'articles en échec</x-mail.heading>

    <x-mail.text>Les <strong>{{ $failures }} dernières générations d'articles</strong> de festilaw.com ont échoué d'affilée. Le site et le back-office ne sont pas touchés : seule la génération par IA est concernée.</x-mail.text>

    @foreach ($rows as $row)
        <x-mail.panel>
            <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                <strong style="color:#0B1E45;">{{ $row['subject'] }}</strong><br>
                <span style="color:#b4532a;">{{ $row['outcome'] }}</span><br>
                <a href="{{ $row['url'] }}" style="color:#0B1E45;">Voir cette génération</a>
            </div>
        </x-mail.panel>
    @endforeach

    <x-mail.button :url="$generationsUrl">Ouvrir les générations</x-mail.button>

    <x-mail.text>La cause technique de chaque échec est écrite dans le journal du serveur (storage/logs/laravel.log), à l'heure de la génération.</x-mail.text>

    <x-mail.text :muted="true" size="13.5px">Cette alerte n'est envoyée qu'une fois par série d'échecs : elle ne se répète pas tant qu'aucune génération n'a réussi entre-temps.</x-mail.text>
</x-mail.layout>
