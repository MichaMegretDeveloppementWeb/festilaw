<x-mail.layout>
    <x-mail.heading>Crédit d'IA épuisé</x-mail.heading>

    <x-mail.text>Le fournisseur d'IA a refusé une génération d'article, parce que <strong>le crédit du compte est épuisé ou que la limite posée sur la clé est atteinte</strong>. La génération d'articles est suspendue jusqu'au rechargement du compte.</x-mail.text>

    <x-mail.text>Le site a enregistré <strong>{{ $spent }}</strong> de dépense ce mois-ci, pour un plafond de {{ $cap }} réglé au back-office.</x-mail.text>

    <x-mail.text>Le site et le back-office fonctionnent normalement. Les articles déjà rédigés restent modifiables et publiables.</x-mail.text>
</x-mail.layout>
