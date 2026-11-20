<x-mail.layout>
    <x-mail.heading>La génération automatique est en pause</x-mail.heading>

    <x-mail.text><strong>{{ $pendingDrafts }} {{ $pendingDrafts > 1 ? 'brouillons générés attendent' : 'brouillon généré attend' }} votre relecture</strong>, ce qui atteint le plafond réglé au back-office. Aucun nouvel article ne sera généré automatiquement tant que ce nombre ne baisse pas.</x-mail.text>

    <x-mail.text>Dès qu'un brouillon est publié ou supprimé, la génération reprend d'elle-même, au prochain lot prévu.</x-mail.text>

    <x-mail.button :url="$draftsUrl">Voir les brouillons à relire</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">Ce message n'est envoyé qu'une fois par pause. Le plafond de brouillons se règle dans les réglages de la génération.</x-mail.text>
</x-mail.layout>
