<x-mail.layout>
    <x-mail.heading>Rapport d'erreurs du matin à l'arrêt</x-mail.heading>

    <x-mail.text>Le rapport d'erreurs de festilaw.com n'a pas tourné depuis le <strong>{{ $lastDoneAt->setTimezone('Europe/Paris')->format('d/m/Y à H:i') }}</strong>, heure de Paris. Il part normalement chaque matin à 6 h UTC, qu'il ait des erreurs à vous envoyer ou non.</x-mail.text>

    <x-mail.panel>
        <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
            <strong style="color:#0B1E45;">Ce que vous ne recevez plus</strong><br>
            Les erreurs du site, celles de la génération d'articles et les problèmes relevés par la vérification quotidienne des services. Les autres tâches automatiques tournent encore.
        </div>
    </x-mail.panel>

    <x-mail.text>À vérifier dans hPanel → Avancé → Tâches cron, avec « Afficher le résultat » : un passage du cron doit se terminer en quelques secondes, et la commande <code>php artisan festilaw:error-report --dry</code> doit répondre sans erreur sur le serveur.</x-mail.text>

    <x-mail.button :url="route('admin.submissions.index')">Ouvrir le back-office</x-mail.button>
</x-mail.layout>
