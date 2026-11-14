<x-mail.layout>
    <x-mail.heading>Générations d'articles en échec</x-mail.heading>

    <x-mail.text>Les <strong>{{ $failures }} dernières générations d'articles</strong> de festilaw.com ont échoué d'affilée. Le site et le back-office ne sont pas touchés : seule la génération par IA est concernée.</x-mail.text>

    @if ($lastError)
        <x-mail.panel>
            <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                <strong style="color:#0B1E45;">Dernière erreur relevée</strong><br>
                {{ $lastError }}
            </div>
        </x-mail.panel>
    @endif

    <x-mail.text>Le détail de chaque étape est dans le journal des générations du back-office, et la trace complète dans le journal de l'application.</x-mail.text>

    <x-mail.text :muted="true" size="13.5px">Cette alerte n'est envoyée qu'une fois par série d'échecs : elle ne se répète pas tant qu'aucune génération n'a réussi entre-temps.</x-mail.text>
</x-mail.layout>
