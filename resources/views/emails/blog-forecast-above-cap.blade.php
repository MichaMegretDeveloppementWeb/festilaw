<x-mail.layout>
    <x-mail.heading>Génération automatique retenue</x-mail.heading>

    <x-mail.text>Le lot d'articles prévu n'a pas été lancé. Avec les réglages en vigueur, le coût estimé d'un article va de {{ $low }} à <strong>{{ $high }}</strong>. C'est au-delà de <strong>{{ $limit }}</strong>, soit le plafond par article de {{ $cap }} augmenté de {{ $percent }} %. Rien n'a été dépensé.</x-mail.text>

    <x-mail.text>Pour que la génération reprenne, baissez la longueur des articles, choisissez des modèles moins chers ou relevez le plafond par article. Le lot partira au prochain passage de la tâche quotidienne dès que l'estimation tiendra sous cette limite.</x-mail.text>

    <x-mail.button :url="$settingsUrl">Ouvrir les réglages</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">Ce message n'est envoyé qu'au début du blocage, pas à chaque passage de la tâche quotidienne.</x-mail.text>
</x-mail.layout>
