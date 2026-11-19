<x-mail.layout>
    @if ($over)
        <x-mail.heading>Plafond de dépense du mois dépassé</x-mail.heading>

        <x-mail.text>La génération d'articles a dépensé <strong>{{ $spent }}</strong> ce mois-ci, au-delà du plafond de <strong>{{ $cap }}</strong> réglé au back-office.</x-mail.text>
    @else
        <x-mail.heading>La dépense du mois approche du plafond</x-mail.heading>

        <x-mail.text>La génération d'articles a dépensé <strong>{{ $spent }}</strong> ce mois-ci, soit {{ $percent }} % du plafond de <strong>{{ $cap }}</strong> réglé au back-office.</x-mail.text>
    @endif

    <x-mail.text>Dépasser le plafond ne bloque pas la génération. Si cette dépense n'est pas prévue, vous pouvez désactiver la génération automatique ou réduire son nombre d'articles. Si elle l'est, vous pouvez relever le plafond.</x-mail.text>

    <x-mail.button :url="$settingsUrl">Ouvrir les réglages</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">Ce message n'est envoyé qu'une fois par mois.</x-mail.text>
</x-mail.layout>
