<x-mail.layout>
    <x-mail.heading>Tâches automatiques à l'arrêt</x-mail.heading>

    @if ($lastRunAt)
        <x-mail.text>Les tâches automatiques de festilaw.com ne tournent plus depuis le <strong>{{ $lastRunAt->format('d/m/Y à H:i') }} (UTC)</strong>.</x-mail.text>
    @else
        <x-mail.text>Les tâches automatiques de festilaw.com ne tournent plus.</x-mail.text>
    @endif

    <x-mail.panel>
        <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
            <strong style="color:#0B1E45;">Ce qui ne s'exécute plus</strong><br>
            Rappels de renouvellement · vérification des paiements et des signatures · récupération des mandats signés · purges RGPD.
        </div>
    </x-mail.panel>

    <x-mail.text>À vérifier dans hPanel → Avancé → Tâches cron : la ligne <code>* * * * * … artisan schedule:run</code> doit exister et s'exécuter chaque minute. Dès qu'elle repart, l'alerte disparaît du back-office.</x-mail.text>

    <x-mail.button :url="route('admin.submissions.index')">Ouvrir le back-office</x-mail.button>

    <x-mail.text :muted="true" size="13.5px">Cette alerte est renvoyée au plus toutes les 6 heures tant que l'arrêt dure.</x-mail.text>
</x-mail.layout>
