<x-mail.layout>
    <x-mail.heading>Génération automatique retenue</x-mail.heading>

    <x-mail.text>Le lot d'articles prévu n'a pas été lancé. Avec les réglages en vigueur, un article coûterait entre {{ $low }} et <strong>{{ $high }}</strong>, alors que le plafond par article est de <strong>{{ $cap }}</strong>. Rien n'a été dépensé.</x-mail.text>

    <x-mail.text>Pour que la génération reprenne, baissez la longueur des articles, choisissez des modèles moins chers ou relevez le plafond par article. Le lot partira au prochain passage de la tâche quotidienne dès que l'estimation tiendra sous le plafond.</x-mail.text>

    <x-mail.button :url="$settingsUrl">Ouvrir les réglages</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">Ce message n'est envoyé qu'au début du blocage, pas à chaque passage de la tâche quotidienne.</x-mail.text>
</x-mail.layout>
