<x-mail.layout>
    @if ($creditExhausted)
        <x-mail.heading>Crédit d'IA épuisé</x-mail.heading>

        <x-mail.text>Le fournisseur d'IA a refusé une génération d'article, parce que <strong>le crédit du compte est épuisé ou que la limite posée sur la clé est atteinte</strong>. La génération d'articles est suspendue jusqu'au rechargement du compte.</x-mail.text>

        <x-mail.text>Le site a enregistré <strong>{{ $spent }}</strong> de dépense ce mois-ci, pour un plafond de {{ $cap }} réglé au back-office.</x-mail.text>
    @else
        <x-mail.heading>Plafond de dépense atteint</x-mail.heading>

        <x-mail.text>La génération d'articles a dépensé <strong>{{ $spent }}</strong> ce mois-ci, pour un plafond de <strong>{{ $cap }}</strong>. Elle est suspendue jusqu'au mois prochain, ou jusqu'à ce que le plafond soit relevé dans les réglages.</x-mail.text>
    @endif

    <x-mail.text>Le site et le back-office fonctionnent normalement. Les articles déjà rédigés restent modifiables et publiables.</x-mail.text>

    <x-mail.text :muted="true" size="13.5px">Ce message n'est envoyé qu'une fois par mois.</x-mail.text>
</x-mail.layout>
